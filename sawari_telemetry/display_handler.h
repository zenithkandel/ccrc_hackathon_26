/**
 * ============================================================================
 * SAWARI Bus Telemetry Device - Display Handler Header
 * ============================================================================
 * Manages 1.3" SH1106 OLED display via I2C using the U8g2 library.
 * Provides status screens for normal operation, GPS search, and WiFi issues.
 * ============================================================================
 */

#ifndef DISPLAY_HANDLER_H
#define DISPLAY_HANDLER_H

#include <Arduino.h>
#include "gps_handler.h"

/**
 * Initialize the OLED display and show a boot splash screen.
 */
void displayInit();

/**
 * Show the main telemetry status screen with GPS data and connectivity info.
 * Enhanced with visual indicators: WiFi bars, speed gauge, direction compass.
 * 
 * Layout (128x64 pixels):
 *   [WiFi Bars] BUS ID: 1
 *   LAT: 27.7123  [Compass]
 *   LON: 85.3123
 *   SPD: 34 km/h [====>    ]
 *   SAT: 9  HDOP: 0.9
 *   [Queue indicator if any]
 * 
 * @param data        Pointer to current telemetry data
 * @param wifiOk      true if WiFi is connected
 * @param wifiRSSI    WiFi signal strength in dBm (-30 to -90)
 * @param queueCount  Number of records in offline queue (shown if > 0)
 */
void displayShowStatus(const TelemetryData* data, bool wifiOk, int wifiRSSI, int queueCount);

/**
 * Show "Searching GPS..." screen when no fix is available.
 * Displays satellite count to show acquisition progress.
 * 
 * @param satellites  Number of satellites currently visible
 * @param wifiOk      true if WiFi is connected
 */
void displaySearchingGPS(int satellites, bool wifiOk);

/**
 * Show the WiFi setup/AP mode screen.
 * Displayed during initial configuration via captive portal.
 */
void displayWiFiSetup();

/**
 * Show animated boot splash with progress bar.
 * @param progress  Progress percentage 0-100
 * @param status    Status text to display below progress bar
 */
void displayBootProgress(int progress, const char* status);

/**
 * Call this in loop() to update animation frames.
 * Manages radar sweep, blinking indicators, etc.
 */
void displayAnimationTick();

#endif // DISPLAY_HANDLER_H
