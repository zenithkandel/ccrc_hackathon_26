/**
 * ============================================================================
 * SAWARI Bus Telemetry Device - Main Firmware
 * ============================================================================
 * 
 * Complete ESP32-based bus tracking telemetry system.
 * 
 * HARDWARE SETUP:
 *   - ESP32 Dev Module
 *   - NEO-6M GPS Module:     TX → GPIO16, RX → GPIO17 (UART2)
 *   - 1.3" OLED (SH1106):    SDA → GPIO21, SCL → GPIO22 (I2C)
 *   - Power LED:              GPIO2  (built-in, always ON)
 *   - WiFi LED:               GPIO4  (ON when connected)
 *   - GPS Lock LED:           GPIO13 (ON when GPS has fix)
 *   - Data Send LED:          GPIO14 (blinks on transmission)
 *   - Power Supply:           12V bus → buck converter → 5V to ESP32 VIN
 * 
 * REQUIRED LIBRARIES (install via Arduino Library Manager):
 *   - TinyGPSPlus        by Mikal Hart
 *   - U8g2               by Oliver Kraus
 *   - WiFiManager        by tzapu (ESP32 branch)
 *   - LittleFS_esp32     (built-in with ESP32 Arduino Core 2.x+)
 * 
 * BOARD SETTINGS:
 *   Board:           ESP32 Dev Module
 *   Partition Scheme: Default 4MB with spiffs (or custom with LittleFS)
 *   Upload Speed:    921600
 *   Flash Frequency: 80MHz
 * 
 * OPERATION FLOW:
 *   1. Power on → LEDs init, OLED splash, LittleFS mount
 *   2. WiFiManager: auto-connect or start captive portal "SAWARI_SETUP"
 *   3. Main loop (non-blocking):
 *      a. Feed GPS parser continuously
 *      b. Every 2s: if GPS fix valid, build JSON and send to server
 *      c. If WiFi down: queue data locally in LittleFS (max 500 records)
 *      d. When WiFi reconnects: flush offline queue automatically
 *      e. Every 500ms: update OLED display with current status
 *      f. Monitor WiFi, manage LEDs, feed watchdog
 *      g. If no GPS fix for 10 minutes: restart ESP32 (watchdog)
 * 
 * ============================================================================
 * 
 * SAWARI Transport Intelligence Platform
 * Firmware Version: 1.0.0
 * Target: ESP32 Dev Module
 * 
 * ============================================================================
 */

// === Core Configuration ===
#include "config.h"

// === Module Headers ===
#include "led_handler.h"
#include "gps_handler.h"
#include "display_handler.h"
#include "storage_handler.h"
#include "network_handler.h"

// === ESP32 Watchdog ===
#include <esp_task_wdt.h>

// ============================================================================
// GLOBAL STATE — Timing variables for non-blocking task scheduling
// ============================================================================

// Last execution timestamps for each periodic task (millis()-based)
static unsigned long lastSendTime     = 0;   // Telemetry data send
static unsigned long lastDisplayTime  = 0;   // OLED display refresh
static unsigned long lastWiFiCheck    = 0;   // WiFi connectivity check
static unsigned long lastQueueFlush   = 0;   // Offline queue flush attempt
static unsigned long lastGpsFixTime   = 0;   // Last time GPS had a valid fix (watchdog)

// Flag to track if we ever got a GPS fix (prevents watchdog restart before first fix)
static bool everHadGpsFix = false;

// ============================================================================
// SETUP — Runs once on boot
// ============================================================================
void setup() {
    // -----------------------------------------------------------------------
    // 1. Initialize serial debug output
    // -----------------------------------------------------------------------
    Serial.begin(115200);
    delay(100);  // Brief delay for serial to stabilize after power-on

    Serial.println();
    Serial.println(F("========================================"));
    Serial.println(F("  SAWARI Bus Telemetry Device v1.0.0"));
    Serial.println(F("  ESP32 Dev Module"));
    Serial.println(F("========================================"));
    Serial.print(F("  Bus ID:  "));
    Serial.println(BUS_ID);
    Serial.print(F("  API:     "));
    Serial.println(API_ENDPOINT);
    Serial.println(F("========================================"));
    Serial.println();

    // -----------------------------------------------------------------------
    // 2. Initialize LED indicators
    //    Power LED turns ON immediately to confirm device is energized.
    // -----------------------------------------------------------------------
    Serial.println(F("[INIT] Initializing LEDs..."));
    ledInit();

    // -----------------------------------------------------------------------
    // 3. Initialize OLED display
    //    Shows boot splash screen while remaining subsystems are starting.
    // -----------------------------------------------------------------------
    Serial.println(F("[INIT] Initializing OLED display..."));
    displayInit();
    delay(500);  // Brief pause to show splash

    // -----------------------------------------------------------------------
    // 4. Initialize LittleFS for offline data storage
    //    Formats the partition on first boot if needed.
    // -----------------------------------------------------------------------
    displayBootProgress(25, "Mounting storage...");
    Serial.println(F("[INIT] Initializing LittleFS storage..."));
    if (!storageInit()) {
        Serial.println(F("[INIT] WARNING: Storage init failed! Offline queue unavailable."));
    }
    delay(200);

    // -----------------------------------------------------------------------
    // 5. Initialize GPS module
    //    Starts UART2 communication with the NEO-6M at 9600 baud.
    //    GPS cold start can take 30-90 seconds for first fix.
    // -----------------------------------------------------------------------
    displayBootProgress(50, "Starting GPS...");
    Serial.println(F("[INIT] Initializing GPS module..."));
    gpsInit();
    delay(200);

    // -----------------------------------------------------------------------
    // 6. Initialize WiFi via WiFiManager
    //    First boot: AP mode with captive portal ("SAWARI_SETUP").
    //    Subsequent boots: auto-connect to saved credentials.
    //    This call may block for up to AP_TIMEOUT seconds if in portal mode.
    // -----------------------------------------------------------------------
    displayBootProgress(75, "Connecting WiFi...");
    Serial.println(F("[INIT] Initializing WiFi..."));
    displayWiFiSetup();  // Show WiFi setup screen on OLED while connecting

    bool wifiConnected = networkInit();
    ledSetWiFi(wifiConnected);

    displayBootProgress(100, wifiConnected ? "WiFi OK!" : "Offline mode");
    delay(500);  // Brief pause to show completion

    if (wifiConnected) {
        Serial.println(F("[INIT] WiFi connected — online mode active"));
    } else {
        Serial.println(F("[INIT] WiFi not connected — offline mode active"));
    }

    // -----------------------------------------------------------------------
    // 7. Initialize hardware watchdog timer
    //    Ensures the device recovers from any software hang.
    //    The main loop must feed the watchdog within HW_WDT_TIMEOUT seconds.
    // -----------------------------------------------------------------------
    Serial.println(F("[INIT] Configuring hardware watchdog..."));
    
    // ESP32 Arduino Core 3.x uses new config struct API
    esp_task_wdt_config_t wdt_config = {
        .timeout_ms = HW_WDT_TIMEOUT * 1000,  // Convert seconds to milliseconds
        .idle_core_mask = (1 << portNUM_PROCESSORS) - 1,  // Watch all cores
        .trigger_panic = true  // Reset on timeout
    };
    esp_task_wdt_init(&wdt_config);
    esp_task_wdt_add(NULL);  // Add current task to WDT

    // -----------------------------------------------------------------------
    // 8. Initialize timing baselines
    // -----------------------------------------------------------------------
    unsigned long now = millis();
    lastSendTime    = now;
    lastDisplayTime = now;
    lastWiFiCheck   = now;
    lastQueueFlush  = now;
    lastGpsFixTime  = now;  // Grace period starts from boot

    Serial.println();
    Serial.println(F("[INIT] ======== INITIALIZATION COMPLETE ========"));
    Serial.println(F("[INIT] Entering main operational loop..."));
    Serial.println();
}

// ============================================================================
// MAIN LOOP — Non-blocking task scheduler using millis()
// ============================================================================
/**
 * The loop uses a cooperative multitasking approach where each task runs
 * at its own interval without blocking other tasks. No delay() calls
 * are used anywhere in the firmware.
 * 
 * Task Schedule:
 *   - GPS Feed:     CONTINUOUS (every iteration)
 *   - Data Send:    Every 2000ms   (SEND_INTERVAL)
 *   - Display:      Every 500ms    (DISPLAY_UPDATE_INTERVAL)
 *   - WiFi Check:   Every 5000ms   (WIFI_CHECK_INTERVAL)
 *   - Queue Flush:  Every 15000ms  (QUEUE_FLUSH_INTERVAL)
 *   - LED Update:   CONTINUOUS (manages blink timing)
 *   - Watchdog:     CONTINUOUS (feeds HW WDT + checks GPS timeout)
 */
void loop() {
    unsigned long now = millis();

    // === Feed hardware watchdog ===
    // This must happen every loop iteration to prevent ESP32 reset.
    esp_task_wdt_reset();

    // ===================================================================
    // TASK 1: GPS DATA FEED (continuous)
    // ===================================================================
    // Feed all available serial bytes to the TinyGPSPlus parser.
    // This must run every iteration to avoid losing NMEA sentences.
    gpsUpdate();

    // Track GPS fix status for LED and watchdog
    bool gpsFix = gpsHasFix();
    ledSetGPS(gpsFix);

    if (gpsFix) {
        lastGpsFixTime = now;
        everHadGpsFix = true;
    }

    // ===================================================================
    // TASK 2: TELEMETRY DATA TRANSMISSION (every SEND_INTERVAL ms)
    // ===================================================================
    if (now - lastSendTime >= SEND_INTERVAL) {
        lastSendTime = now;

        // Only attempt to send if GPS has a valid fix
        if (gpsFix) {
            // Build telemetry data structure from GPS readings
            TelemetryData telemetry;
            gpsGetTelemetry(&telemetry);

            // Format as JSON payload
            String payload = gpsFormatPayload(&telemetry);

            if (networkIsConnected()) {
                // --- ONLINE: Send directly to API ---
                Serial.println(F("[MAIN] Sending telemetry to server..."));
                bool sent = networkSendData(payload);

                if (sent) {
                    ledBlinkData();  // Visual confirmation of successful send
                } else {
                    // HTTP request failed — queue for retry
                    Serial.println(F("[MAIN] Send failed — queuing for retry"));
                    storageEnqueue(payload);
                }
            } else {
                // --- OFFLINE: Queue data locally ---
                Serial.println(F("[MAIN] WiFi offline — queuing telemetry data"));
                storageEnqueue(payload);
            }
        }
        // If no GPS fix, do nothing — don't send invalid data
    }

    // ===================================================================
    // TASK 3: OLED DISPLAY UPDATE (every DISPLAY_UPDATE_INTERVAL ms)
    // ===================================================================
    if (now - lastDisplayTime >= DISPLAY_UPDATE_INTERVAL) {
        lastDisplayTime = now;

        bool wifiOk = networkIsConnected();
        int wifiRSSI = networkGetRSSI();
        int queueCount = storageGetCount();

        if (gpsFix) {
            // Show full telemetry status screen with visual enhancements
            TelemetryData displayData;
            gpsGetTelemetry(&displayData);
            displayShowStatus(&displayData, wifiOk, wifiRSSI, queueCount);
        } else {
            // Show GPS search screen with animated radar
            int sats = gpsGetSatellites();
            displaySearchingGPS(sats, wifiOk);
        }
    }

    // ===================================================================
    // TASK 4: WIFI CONNECTIVITY CHECK (every WIFI_CHECK_INTERVAL ms)
    // ===================================================================
    if (now - lastWiFiCheck >= WIFI_CHECK_INTERVAL) {
        lastWiFiCheck = now;

        // Update WiFi LED
        bool wifiOk = networkIsConnected();
        ledSetWiFi(wifiOk);

        // Attempt reconnection if disconnected
        if (!wifiOk) {
            networkCheckReconnect();
        }
    }

    // ===================================================================
    // TASK 5: OFFLINE QUEUE FLUSH (every QUEUE_FLUSH_INTERVAL ms)
    // ===================================================================
    // When WiFi reconnects, periodically attempt to send queued records.
    if (now - lastQueueFlush >= QUEUE_FLUSH_INTERVAL) {
        lastQueueFlush = now;

        if (networkIsConnected() && storageGetCount() > 0) {
            Serial.println(F("[MAIN] WiFi available — flushing offline queue..."));

            // The flush function calls networkSendData for each record.
            // It stops on the first failure to avoid blocking too long.
            int sent = storageFlush([](const String& json) -> bool {
                bool success = networkSendData(json);
                if (success) {
                    ledBlinkData();  // Blink for each successful send
                }
                return success;
            });

            if (sent > 0) {
                Serial.print(F("[MAIN] Flushed "));
                Serial.print(sent);
                Serial.println(F(" queued records"));
            }
        }
    }

    // ===================================================================
    // TASK 6: LED & DISPLAY ANIMATION UPDATE (continuous)
    // ===================================================================
    // Handles non-blocking blink timing for the data LED
    ledUpdate();
    // Update display animations (radar sweep, blinking indicators)
    displayAnimationTick();

    // ===================================================================
    // TASK 7: GPS WATCHDOG — Restart if no fix for 10 minutes
    // ===================================================================
    // This protects against GPS module lockup or antenna disconnection.
    // Only active after the initial grace period (first fix must be obtained)
    // or after a previously valid fix is lost for too long.
    //
    // Note: Cold start GPS acquisition can take several minutes, so this
    // watchdog only activates if we had a fix and then lost it, OR if
    // the device has been running for longer than GPS_WATCHDOG_TIMEOUT
    // without ever getting a fix (indicates hardware problem).
    if (now - lastGpsFixTime >= GPS_WATCHDOG_TIMEOUT) {
        if (everHadGpsFix || now > GPS_WATCHDOG_TIMEOUT * 2) {
            Serial.println(F("[WATCHDOG] No GPS fix for 10 minutes — RESTARTING ESP32"));
            Serial.flush();
            ESP.restart();
        }
    }

    // === Small yield to prevent WDT issues on tight loops ===
    // ESP32 FreeRTOS needs occasional yields for background tasks
    yield();
}
