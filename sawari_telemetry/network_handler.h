/**
 * ============================================================================
 * SAWARI Bus Telemetry Device - Network Handler Header
 * ============================================================================
 * Manages WiFi connectivity via WiFiManager and HTTP POST data transmission.
 * Supports on-demand captive portal via button press, auto-reconnection,
 * and automatic portal close on successful WiFi connection.
 * ============================================================================
 */

#ifndef NETWORK_HANDLER_H
#define NETWORK_HANDLER_H

#include <Arduino.h>

/**
 * Initialize WiFi using WiFiManager.
 * On first boot (no saved credentials): starts AP with captive portal.
 * On subsequent boots: auto-connects to saved WiFi network.
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
 * Call this periodically from loop() (every WIFI_CHECK_INTERVAL).
 */
void networkCheckReconnect();

/**
 * Start the WiFiManager captive portal on demand (e.g. button press).
 * This is NON-BLOCKING when used with networkPortalLoop().
 * The portal auto-closes when a client successfully connects.
 * @return true if the portal was started successfully
 */
bool networkStartPortal();

/**
 * Process captive portal requests. Must be called in loop() while the
 * portal is active. Returns true when a WiFi connection is established
 * (portal should then be stopped).
 * @return true if WiFi connected through the portal
 */
bool networkPortalLoop();

/**
 * Stop the captive portal and resume normal WiFi operation.
 */
void networkStopPortal();

/**
 * Check whether the captive portal is currently running.
 * @return true if portal is active
 */
bool networkIsPortalActive();

/**
 * Send a JSON payload to the configured API endpoint via HTTP POST.
 * @param json  JSON string to send as request body
 * @return true if HTTP response code is 2xx (success)
 */
bool networkSendData(const String& json);

/**
 * Get the device's current local IP address as a string.
 * @return IP address string, or "0.0.0.0" if not connected
 */
String networkGetIP();

/**
 * Get the portal AP IP address (typically "192.168.4.1").
 * @return AP IP string
 */
String networkGetPortalIP();

/**
 * Get current WiFi signal strength (RSSI) in dBm.
 * @return RSSI value (-30 excellent to -90 weak), or -100 if not connected
 */
int networkGetRSSI();

/**
 * Get the SSID of the currently connected WiFi network.
 * @return SSID string, or "" if not connected
 */
String networkGetSSID();

#endif // NETWORK_HANDLER_H
