/**
 * ============================================================================
 * SAWARI Bus Telemetry Device - LED Handler Header
 * ============================================================================
 * Manages four status LEDs with non-blocking blink support for the data LED.
 * ============================================================================
 */

#ifndef LED_HANDLER_H
#define LED_HANDLER_H

#include <Arduino.h>

/**
 * Initialize all LED pins as OUTPUT and set initial states.
 * Power LED is turned ON immediately.
 */
void ledInit();

/**
 * Set the WiFi status LED.
 * @param on true = LED ON (WiFi connected), false = LED OFF
 */
void ledSetWiFi(bool on);

/**
 * Set the GPS lock status LED.
 * @param on true = LED ON (GPS fix acquired), false = LED OFF
 */
void ledSetGPS(bool on);

/**
 * Trigger a non-blocking blink on the Data Send LED.
 * The LED turns ON and automatically turns OFF after DATA_LED_BLINK_MS.
 */
void ledBlinkData();

/**
 * Must be called in loop() to handle non-blocking LED timing (data blink).
 */
void ledUpdate();

#endif // LED_HANDLER_H
