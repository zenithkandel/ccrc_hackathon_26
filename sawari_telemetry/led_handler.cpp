/**
 * ============================================================================
 * SAWARI Bus Telemetry Device - LED Handler Implementation
 * ============================================================================
 * 
 * Controls four status LEDs:
 *   - Power LED:     Always ON after initialization
 *   - WiFi LED:      ON when WiFi is connected
 *   - GPS Lock LED:  ON when GPS has a valid fix
 *   - Data Send LED: Blinks briefly each time data is transmitted
 * 
 * The data LED uses non-blocking timing via millis() so it does not
 * interfere with the main loop execution.
 * ============================================================================
 */

#include "led_handler.h"
#include "config.h"

// --- Internal state for non-blocking data LED blink ---
static bool     _dataLedActive = false;
static unsigned long _dataLedOnTime = 0;

/**
 * Initialize all LED GPIO pins and set default states.
 */
void ledInit() {
    pinMode(LED_POWER, OUTPUT);
    pinMode(LED_WIFI,  OUTPUT);
    pinMode(LED_GPS,   OUTPUT);
    pinMode(LED_DATA,  OUTPUT);

    // Power LED is always ON to indicate the device is energized
    digitalWrite(LED_POWER, HIGH);

    // All other LEDs start OFF
    digitalWrite(LED_WIFI, LOW);
    digitalWrite(LED_GPS,  LOW);
    digitalWrite(LED_DATA, LOW);
}

/**
 * Set WiFi LED state.
 */
void ledSetWiFi(bool on) {
    digitalWrite(LED_WIFI, on ? HIGH : LOW);
}

/**
 * Set GPS Lock LED state.
 */
void ledSetGPS(bool on) {
    digitalWrite(LED_GPS, on ? HIGH : LOW);
}

/**
 * Trigger a non-blocking data LED blink.
 * Turns the LED ON; ledUpdate() will turn it OFF after the blink duration.
 */
void ledBlinkData() {
    digitalWrite(LED_DATA, HIGH);
    _dataLedActive = true;
    _dataLedOnTime = millis();
}

/**
 * Non-blocking LED update. Call this every loop() iteration.
 * Handles auto-off for the data LED blink.
 */
void ledUpdate() {
    // Turn off data LED after blink duration has elapsed
    if (_dataLedActive && (millis() - _dataLedOnTime >= DATA_LED_BLINK_MS)) {
        digitalWrite(LED_DATA, LOW);
        _dataLedActive = false;
    }
}
