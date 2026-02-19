/**
 * ============================================================================
 * SAWARI Bus Telemetry Device - Configuration Header
 * ============================================================================
 * 
 * Central configuration file for all hardware pins, timing constants,
 * network settings, and operational parameters.
 * 
 * Hardware: ESP32 Dev Module + NEO-6M GPS + 1.3" OLED (SH1106)
 * 
 * IMPORTANT: Update API_ENDPOINT and BUS_ID before deployment.
 * ============================================================================
 */

#ifndef CONFIG_H
#define CONFIG_H

// ============================================================================
// BUS IDENTIFICATION
// ============================================================================
// Unique identifier for this bus unit. Must match the server-side record.
#define BUS_ID              1

// ============================================================================
// PIN DEFINITIONS — GPS MODULE (NEO-6M via UART2)
// ============================================================================
// ESP32 UART2 is used for GPS communication.
// Wiring: GPS TX → ESP32 GPIO16 (RX2), GPS RX → ESP32 GPIO17 (TX2)
#define GPS_RX_PIN          16
#define GPS_TX_PIN          17
#define GPS_BAUD            9600

// ============================================================================
// PIN DEFINITIONS — OLED DISPLAY (1.3" SH1106, I2C)
// ============================================================================
// ESP32 default I2C: SDA = GPIO21, SCL = GPIO22
// These are the hardware I2C defaults; U8G2 HW_I2C uses them automatically.
#define OLED_SDA            21
#define OLED_SCL            22

// ============================================================================
// PIN DEFINITIONS — STATUS LEDs
// ============================================================================
// Four indicator LEDs for visual status feedback.
// Choose GPIOs that do not conflict with ESP32 boot strapping pins.
#define LED_POWER           2       // Power indicator (GPIO2 = built-in LED)
#define LED_WIFI            4       // WiFi connection status
#define LED_GPS             13      // GPS fix lock indicator
#define LED_DATA            14      // Data transmission blink

// ============================================================================
// NETWORK CONFIGURATION
// ============================================================================
// WiFiManager access point name for first-boot configuration
#define AP_NAME             "SAWARI_SETUP"

// Captive portal timeout in seconds (falls back to offline mode after this)
#define AP_TIMEOUT          180

// API endpoint for telemetry data submission
// UPDATE THIS to your actual server URL before deploying
#define API_ENDPOINT        "https://zenithkandel.com.np/test api/api.php"

// HTTP request timeout in milliseconds
#define HTTP_TIMEOUT        5000

// ============================================================================
// TIMING INTERVALS (all in milliseconds)
// ============================================================================
// How often to send GPS data to the server
#define SEND_INTERVAL               2000

// How often to refresh the OLED display
#define DISPLAY_UPDATE_INTERVAL     500

// How often to check WiFi connectivity
#define WIFI_CHECK_INTERVAL         5000

// How often to attempt flushing the offline queue
#define QUEUE_FLUSH_INTERVAL        15000

// GPS watchdog: restart ESP32 if no GPS fix for this duration
#define GPS_WATCHDOG_TIMEOUT        600000      // 10 minutes

// Data LED blink duration
#define DATA_LED_BLINK_MS           150

// WiFi reconnect cooldown (avoid spamming reconnect attempts)
#define WIFI_RECONNECT_INTERVAL     30000

// ============================================================================
// OFFLINE STORAGE CONFIGURATION
// ============================================================================
// File path on LittleFS for the offline data queue
#define QUEUE_FILE          "/queue.jsonl"

// Maximum number of records to keep in offline queue.
// Oldest records are discarded when this limit is exceeded.
#define MAX_QUEUE_SIZE      500

// ============================================================================
// HARDWARE WATCHDOG
// ============================================================================
// ESP32 Task Watchdog timeout in seconds.
// The loop must feed the watchdog within this interval.
#define HW_WDT_TIMEOUT      30

// ============================================================================
// AUTOMOTIVE POWER NOTES
// ============================================================================
// The ESP32 is powered via a buck converter (e.g., LM2596, MP1584)
// stepping down 12V vehicle bus to stable 5V for the dev module's USB/VIN.
//
// Software mitigations for automotive environment:
//   - Brown-out detector is enabled by default on ESP32
//   - Watchdog timer ensures recovery from hangs
//   - LittleFS journaling protects against power-loss corruption
//   - Non-blocking loop prevents single-task lockups
// ============================================================================

#endif // CONFIG_H
