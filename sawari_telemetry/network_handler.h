/**
 * ============================================================================
 * SAWARI Bus Telemetry Device - Network Handler Header
 * ============================================================================
 * Manages WiFi connectivity via WiFiManager and HTTP POST data transmission.
 * Handles auto-reconnection and captive portal fallback.
 * ============================================================================
 */

#ifndef NETWORK_HANDLER_H
#define NETWORK_HANDLER_H

#include <Arduino.h>

/**
 * Initialize WiFi using WiFiManager.
 * 
 * Behavior:
 *   - On first boot (no saved credentials): starts AP "SAWARI_SETUP"
 *     with captive portal and blocks for up to AP_TIMEOUT seconds.
 *   - On subsequent boots: auto-connects to saved WiFi network.
 *   - If saved network is unreachable: falls back to AP mode.
 * 
 * @return true if WiFi connected successfully, false if timed out
 */
bool networkInit();

/**
 * Check current WiFi connection status.
 * @return true if WiFi is connected
 */
bool networkIsConnected();

/**
 * Attempt to reconnect WiFi if disconnected.
 * Uses a cooldown interval to avoid spamming reconnect attempts.
 * Call this periodically from loop().
 */
void networkCheckReconnect();

/**
 * Send a JSON payload to the configured API endpoint via HTTP POST.
 * 
 * @param json  JSON string to send as request body
 * @return true if HTTP response code is 2xx (success)
 */
bool networkSendData(const String& json);

/**
 * Get the device's current local IP address as a string.
 * Useful for debug output.
 * @return IP address string, or "0.0.0.0" if not connected
 */
String networkGetIP();

/**
 * Get current WiFi signal strength (RSSI) in dBm.
 * @return RSSI value (-30 excellent to -90 weak), or -100 if not connected
 */
int networkGetRSSI();

#endif // NETWORK_HANDLER_H
