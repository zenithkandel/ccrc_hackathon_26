/**
 * ============================================================================
 * SAWARI Bus Telemetry Device - Display Handler Header
 * ============================================================================
 * Manages 1.3" SH1106 OLED display via I2C using the U8g2 library.
 *
 * Screen Flow:
 *   1. Boot Splash       — Logo + version on power-on
 *   2. Boot Progress     — Progress bar with status text + WiFi connection state
 *   3. WiFi Portal       — AP name, IP, portal status while captive portal open
 *   4. GPS Search        — Animated radar while waiting for satellite fix
 *   5. Main Telemetry    — Lat, Lon, Speed, Direction, Sats, HDOP, WiFi info
 *   6. Offline Banner    — Shown when operating without WiFi (queue count)
 * ============================================================================
 */

#ifndef DISPLAY_HANDLER_H
#define DISPLAY_HANDLER_H

#include <Arduino.h>
#include "gps_handler.h"

/** Initialize the OLED display and show the boot splash screen. */
void displayInit();

/** Show animated boot progress bar with ESP connection status. */
void displayBootProgress(int progress, const char* status);

/** Show WiFi setup / captive portal screen (initial boot). */
void displayWiFiSetup();

/** Show WiFi portal active screen with AP name and portal IP. */
void displayPortalActive(const char* apName, const char* portalIP);

/** Show "Connecting WiFi..." with spinner animation. */
void displayConnectingWiFi();

/** Show WiFi connected confirmation with SSID, IP, and RSSI. */
void displayWiFiConnected(const char* ssid, const char* ip, int rssi);

/**
 * Show GPS searching screen with animated radar.
 * Includes WiFi SSID and offline queue count.
 */
void displaySearchingGPS(int satellites, bool wifiOk, const char* wifiSSID, int queueCount);

/**
 * Show the main telemetry data screen.
 * Displays all GPS data, WiFi info, and online/offline mode.
 */
void displayShowStatus(const TelemetryData* data, bool wifiOk, int wifiRSSI,
                       const char* wifiSSID, int queueCount, bool isOffline);

/** Show offline mode info screen with queue count and retry countdown. */
void displayOfflineMode(int queueCount, int secUntilRetry);

/** Call frequently in loop() to update animation frames (radar, blink). */
void displayAnimationTick();

#endif // DISPLAY_HANDLER_H
