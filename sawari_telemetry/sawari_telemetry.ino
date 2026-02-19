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
 *   - BOOT Button:            GPIO0  (long-press opens WiFi portal)
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
 *   1. Power on → LEDs init, OLED boot splash with progress, LittleFS mount
 *   2. WiFiManager: auto-connect or start captive portal "SAWARI_SETUP"
 *   3. Display shows connection status (connected SSID / offline mode)
 *   4. Main loop (non-blocking):
 *      a. Feed GPS parser continuously
 *      b. Every 2s: if GPS fix valid, build JSON and send to server
 *      c. If WiFi down: queue data locally in LittleFS (max 500 records)
 *      d. Every 10s: check WiFi availability, auto-reconnect if possible
 *      e. When WiFi reconnects: flush offline queue automatically
 *      f. Every 500ms: update OLED with lat, lon, speed, WiFi info, mode
 *      g. BOOT button long-press: open WiFi config portal on OLED
 *      h. Portal auto-closes on successful connection, display updates
 *      i. Monitor WiFi, manage LEDs, feed watchdog
 *      j. If no GPS fix for 10 minutes: restart ESP32 (watchdog)
 *
 * ============================================================================
 * SAWARI Transport Intelligence Platform
 * Firmware Version: 2.0.0
 * Target: ESP32 Dev Module
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
// GLOBAL STATE
// ============================================================================

// Task scheduling timestamps (millis()-based, non-blocking)
static unsigned long lastSendTime     = 0;
static unsigned long lastDisplayTime  = 0;
static unsigned long lastWiFiCheck    = 0;
static unsigned long lastQueueFlush   = 0;
static unsigned long lastGpsFixTime   = 0;

// GPS watchdog tracking
static bool everHadGpsFix = false;

// --- BOOT Button state ---
static bool     buttonPressed       = false;
static unsigned long buttonDownTime = 0;

// --- WiFi / Offline mode tracking ---
static bool isOfflineMode = false;           // true = WiFi unavailable, storing locally

// Cached WiFi SSID for display (avoids repeated WiFi.SSID() calls)
static char cachedSSID[33] = "";

// ============================================================================
// HELPER: Update cached Wi-Fi SSID
// ============================================================================
static void updateCachedSSID() {
    if (networkIsConnected()) {
        String ssid = networkGetSSID();
        strncpy(cachedSSID, ssid.c_str(), sizeof(cachedSSID) - 1);
        cachedSSID[sizeof(cachedSSID) - 1] = '\0';
    } else {
        cachedSSID[0] = '\0';
    }
}

// ============================================================================
// HELPER: Handle BOOT button (GPIO0) for WiFi portal
// ============================================================================
static void handleBootButton() {
    bool currentlyPressed = (digitalRead(BUTTON_BOOT) == LOW);  // Active LOW

    if (currentlyPressed && !buttonPressed) {
        // Button just pressed — record the time
        buttonPressed = true;
        buttonDownTime = millis();
    }
    else if (!currentlyPressed && buttonPressed) {
        // Button released — check if it was a long press
        unsigned long pressDuration = millis() - buttonDownTime;
        buttonPressed = false;

        if (pressDuration >= BUTTON_LONG_PRESS_MS) {
            // Long press detected — open WiFi portal
            Serial.println(F("[BUTTON] Long press detected — opening WiFi portal"));

            if (!networkIsPortalActive()) {
                displayPortalActive(AP_NAME, networkGetPortalIP().c_str());
                networkStartPortal();
            }
        }
    }
}

// ============================================================================
// SETUP — Runs once on boot
// ============================================================================
void setup() {
    // --- 1. Serial debug ---
    Serial.begin(115200);
    delay(100);

    Serial.println();
    Serial.println(F("========================================"));
    Serial.println(F("  SAWARI Bus Telemetry Device v2.0.0"));
    Serial.println(F("  ESP32 Dev Module"));
    Serial.println(F("========================================"));
    Serial.print(F("  Bus ID:  "));
    Serial.println(BUS_ID);
    Serial.print(F("  API:     "));
    Serial.println(API_ENDPOINT);
    Serial.println(F("========================================"));
    Serial.println();

    // --- 2. LEDs ---
    Serial.println(F("[INIT] Initializing LEDs..."));
    ledInit();

    // --- 3. OLED boot splash ---
    Serial.println(F("[INIT] Initializing OLED display..."));
    displayInit();
    delay(800);

    // --- 4. BOOT button pin ---
    pinMode(BUTTON_BOOT, INPUT_PULLUP);

    // --- 5. LittleFS storage ---
    displayBootProgress(20, "Mounting storage...");
    Serial.println(F("[INIT] Initializing LittleFS storage..."));
    if (!storageInit()) {
        Serial.println(F("[INIT] WARNING: Storage init failed!"));
        displayBootProgress(20, "Storage FAILED!");
        delay(500);
    }
    delay(200);

    // --- 6. GPS module ---
    displayBootProgress(40, "Starting GPS...");
    Serial.println(F("[INIT] Initializing GPS module..."));
    gpsInit();
    delay(200);

    // --- 7. WiFi connection ---
    displayBootProgress(60, "Connecting WiFi...");
    Serial.println(F("[INIT] Initializing WiFi..."));
    displayWiFiSetup();

    bool wifiConnected = networkInit();
    ledSetWiFi(wifiConnected);

    if (wifiConnected) {
        isOfflineMode = false;
        updateCachedSSID();
        displayBootProgress(90, "WiFi Connected!");
        delay(400);

        // Show connected confirmation screen
        displayWiFiConnected(cachedSSID, networkGetIP().c_str(), networkGetRSSI());
        delay(1500);

        Serial.println(F("[INIT] WiFi connected — online mode active"));
    } else {
        isOfflineMode = true;
        displayBootProgress(90, "WiFi FAILED-Offline");
        delay(500);

        Serial.println(F("[INIT] WiFi not connected — offline mode active"));
        Serial.println(F("[INIT] Data will be stored locally and synced when WiFi is available"));
    }

    displayBootProgress(100, "System Ready!");
    delay(400);

    // --- 8. Hardware watchdog ---
    Serial.println(F("[INIT] Configuring hardware watchdog..."));
    esp_task_wdt_config_t wdt_config = {
        .timeout_ms = HW_WDT_TIMEOUT * 1000,
        .idle_core_mask = (1 << portNUM_PROCESSORS) - 1,
        .trigger_panic = true
    };
    esp_task_wdt_init(&wdt_config);
    esp_task_wdt_add(NULL);

    // --- 9. Timing baselines ---
    unsigned long now = millis();
    lastSendTime    = now;
    lastDisplayTime = now;
    lastWiFiCheck   = now;
    lastQueueFlush  = now;
    lastGpsFixTime  = now;

    Serial.println();
    Serial.println(F("[INIT] ======== INITIALIZATION COMPLETE ========"));
    Serial.println(F("[INIT] Entering main operational loop..."));
    Serial.println(F("[INIT] Hold BOOT button (2s) to open WiFi portal"));
    Serial.println();
}

// ============================================================================
// MAIN LOOP — Non-blocking task scheduler
// ============================================================================
void loop() {
    unsigned long now = millis();

    // === Feed hardware watchdog ===
    esp_task_wdt_reset();

    // ===================================================================
    // TASK 1: GPS DATA FEED (continuous)
    // ===================================================================
    gpsUpdate();

    bool gpsFix = gpsHasFix();
    ledSetGPS(gpsFix);

    if (gpsFix) {
        lastGpsFixTime = now;
        everHadGpsFix = true;
    }

    // ===================================================================
    // TASK 2: BOOT BUTTON — WiFi portal trigger (continuous)
    // ===================================================================
    handleBootButton();

    // ===================================================================
    // TASK 3: WiFi PORTAL PROCESSING (while portal is active)
    // ===================================================================
    if (networkIsPortalActive()) {
        bool connected = networkPortalLoop();
        if (connected) {
            // Portal auto-closed after successful connection
            isOfflineMode = false;
            updateCachedSSID();
            ledSetWiFi(true);

            // Show connection success on OLED
            displayWiFiConnected(cachedSSID, networkGetIP().c_str(), networkGetRSSI());
            delay(2000);

            Serial.println(F("[MAIN] WiFi connected via portal — switching to online mode"));

            // Flush any queued offline data
            if (storageGetCount() > 0) {
                Serial.println(F("[MAIN] Flushing offline queue after portal connect..."));
                storageFlush([](const String& json) -> bool {
                    bool success = networkSendData(json);
                    if (success) ledBlinkData();
                    return success;
                });
            }
        }

        // While portal is active, keep updating the portal display
        if (networkIsPortalActive()) {
            displayAnimationTick();
            if (now - lastDisplayTime >= DISPLAY_UPDATE_INTERVAL) {
                lastDisplayTime = now;
                displayPortalActive(AP_NAME, networkGetPortalIP().c_str());
            }
        }

        // Skip other display/wifi tasks while portal is open, but keep GPS feeding
        ledUpdate();
        yield();
        return;
    }

    // ===================================================================
    // TASK 4: TELEMETRY DATA TRANSMISSION (every SEND_INTERVAL ms)
    // ===================================================================
    if (now - lastSendTime >= SEND_INTERVAL) {
        lastSendTime = now;

        if (gpsFix) {
            TelemetryData telemetry;
            gpsGetTelemetry(&telemetry);
            String payload = gpsFormatPayload(&telemetry);

            if (networkIsConnected()) {
                // --- ONLINE: Send directly ---
                Serial.println(F("[MAIN] Sending telemetry to server..."));
                bool sent = networkSendData(payload);

                if (sent) {
                    ledBlinkData();
                } else {
                    Serial.println(F("[MAIN] Send failed — queuing for retry"));
                    storageEnqueue(payload);
                }
            } else {
                // --- OFFLINE: Queue locally ---
                Serial.println(F("[MAIN] WiFi offline — queuing telemetry data"));
                storageEnqueue(payload);
            }
        }
    }

    // ===================================================================
    // TASK 5: OLED DISPLAY UPDATE (every DISPLAY_UPDATE_INTERVAL ms)
    // ===================================================================
    if (now - lastDisplayTime >= DISPLAY_UPDATE_INTERVAL) {
        lastDisplayTime = now;

        bool wifiOk = networkIsConnected();
        int wifiRSSI = networkGetRSSI();
        int queueCount = storageGetCount();

        if (gpsFix) {
            // Main telemetry screen with all data
            TelemetryData displayData;
            gpsGetTelemetry(&displayData);
            displayShowStatus(&displayData, wifiOk, wifiRSSI,
                              wifiOk ? cachedSSID : "OFFLINE",
                              queueCount, isOfflineMode);
        } else {
            // GPS search screen with WiFi info
            int sats = gpsGetSatellites();
            displaySearchingGPS(sats, wifiOk,
                                wifiOk ? cachedSSID : "N/A",
                                queueCount);
        }
    }

    // ===================================================================
    // TASK 6: WIFI CHECK & AUTO-RECONNECT (every WIFI_CHECK_INTERVAL = 10s)
    // ===================================================================
    if (now - lastWiFiCheck >= WIFI_CHECK_INTERVAL) {
        lastWiFiCheck = now;

        bool wifiOk = networkIsConnected();
        ledSetWiFi(wifiOk);

        if (wifiOk && isOfflineMode) {
            // WiFi came back! Switch from offline → online
            isOfflineMode = false;
            updateCachedSSID();
            Serial.println(F("[MAIN] WiFi restored — switching to online mode"));

            // Show brief connection notification
            displayWiFiConnected(cachedSSID, networkGetIP().c_str(), networkGetRSSI());
            delay(1000);
        }
        else if (!wifiOk && !isOfflineMode) {
            // WiFi lost — switch to offline mode
            isOfflineMode = true;
            Serial.println(F("[MAIN] WiFi lost — switching to offline mode"));
            Serial.println(F("[MAIN] Data will be stored locally"));
        }

        if (!wifiOk) {
            networkCheckReconnect();
            Serial.print(F("[MAIN] WiFi offline — next check in "));
            Serial.print(WIFI_CHECK_INTERVAL / 1000);
            Serial.println(F("s. Hold BOOT (2s) for portal."));
        } else {
            // Refresh cached SSID periodically
            updateCachedSSID();
        }
    }

    // ===================================================================
    // TASK 7: OFFLINE QUEUE FLUSH (every QUEUE_FLUSH_INTERVAL ms)
    // ===================================================================
    if (now - lastQueueFlush >= QUEUE_FLUSH_INTERVAL) {
        lastQueueFlush = now;

        if (networkIsConnected() && storageGetCount() > 0) {
            Serial.println(F("[MAIN] WiFi available — flushing offline queue..."));

            int sent = storageFlush([](const String& json) -> bool {
                bool success = networkSendData(json);
                if (success) ledBlinkData();
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
    // TASK 8: LED & DISPLAY ANIMATION UPDATE (continuous)
    // ===================================================================
    ledUpdate();
    displayAnimationTick();

    // ===================================================================
    // TASK 9: GPS WATCHDOG — Restart if no fix for 10 minutes
    // ===================================================================
    if (now - lastGpsFixTime >= GPS_WATCHDOG_TIMEOUT) {
        if (everHadGpsFix || now > GPS_WATCHDOG_TIMEOUT * 2) {
            Serial.println(F("[WATCHDOG] No GPS fix for 10 minutes — RESTARTING ESP32"));
            Serial.flush();
            ESP.restart();
        }
    }

    // === Yield for FreeRTOS background tasks ===
    yield();
}
