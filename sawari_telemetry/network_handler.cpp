/**
 * ============================================================================
 * SAWARI Bus Telemetry Device - Network Handler Implementation
 * ============================================================================
 * 
 * WiFi connectivity management using WiFiManager library (tzapu/WiFiManager).
 * 
 * WiFiManager Flow:
 *   1. On boot, WiFiManager checks for saved WiFi credentials in flash.
 *   2. If credentials exist, it attempts to connect to the saved network.
 *   3. If connection fails (or no credentials), it starts an access point
 *      named "SAWARI_SETUP" with a captive portal at 192.168.4.1.
 *   4. The user connects to the AP from their phone/laptop, selects their
 *      WiFi network, enters the password, and the device connects.
 *   5. Credentials are saved in flash for subsequent boots.
 * 
 * HTTP Communication:
 *   - Uses ESP32 HTTPClient to POST JSON telemetry to the server API.
 *   - Includes timeout handling and error logging.
 *   - Returns success/failure so the caller can decide to queue or discard.
 * 
 * Auto-Reconnect:
 *   - If WiFi drops during operation, the handler attempts reconnection
 *     at regular intervals (WIFI_RECONNECT_INTERVAL).
 *   - Data continues to be queued offline during disconnection.
 * ============================================================================
 */

#include "network_handler.h"
#include "config.h"
#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiManager.h>

// --- Reconnection timing state ---
static unsigned long _lastReconnectAttempt = 0;
static bool _wasConnected = false;

/**
 * Initialize WiFi using WiFiManager with captive portal support.
 * 
 * This call is BLOCKING during AP mode — it waits for the user to
 * configure WiFi via the captive portal, up to AP_TIMEOUT seconds.
 * After timeout, execution continues (device operates in offline mode).
 */
bool networkInit() {
    WiFiManager wm;

    // Timeout for the captive portal (seconds).
    // After this, setup() continues regardless of WiFi status
    // so GPS and offline queuing can still operate.
    wm.setConfigPortalTimeout(AP_TIMEOUT);

    // Set connection timeout for attempts to saved network
    wm.setConnectTimeout(15);

    // Clean any previously incomplete connections
    wm.setCleanConnect(true);

    Serial.println(F("[NETWORK] Starting WiFiManager..."));
    Serial.print(F("[NETWORK] AP Name: "));
    Serial.println(AP_NAME);

    // autoConnect tries saved credentials first.
    // If that fails, starts AP with captive portal.
    bool connected = wm.autoConnect(AP_NAME);

    if (connected) {
        Serial.println(F("[NETWORK] WiFi connected successfully"));
        Serial.print(F("[NETWORK] IP Address: "));
        Serial.println(WiFi.localIP());
        Serial.print(F("[NETWORK] RSSI: "));
        Serial.print(WiFi.RSSI());
        Serial.println(F(" dBm"));
        _wasConnected = true;
    } else {
        Serial.println(F("[NETWORK] WiFi connection failed / portal timed out"));
        Serial.println(F("[NETWORK] Operating in offline mode (GPS + queue active)"));
    }

    return connected;
}

/**
 * Check if WiFi is currently connected.
 */
bool networkIsConnected() {
    return WiFi.status() == WL_CONNECTED;
}

/**
 * Periodically attempt WiFi reconnection if disconnected.
 * Uses cooldown interval to prevent rapid reconnect spam which
 * can destabilize the WiFi stack.
 */
void networkCheckReconnect() {
    bool currentlyConnected = networkIsConnected();

    // Log state transitions for debugging
    if (_wasConnected && !currentlyConnected) {
        Serial.println(F("[NETWORK] WiFi connection LOST — switching to offline mode"));
        _wasConnected = false;
    } else if (!_wasConnected && currentlyConnected) {
        Serial.println(F("[NETWORK] WiFi RECONNECTED"));
        Serial.print(F("[NETWORK] IP: "));
        Serial.println(WiFi.localIP());
        _wasConnected = true;
        return;
    }

    // If disconnected, attempt reconnect at regular intervals
    if (!currentlyConnected) {
        unsigned long now = millis();
        if (now - _lastReconnectAttempt >= WIFI_RECONNECT_INTERVAL) {
            _lastReconnectAttempt = now;
            Serial.println(F("[NETWORK] Attempting WiFi reconnect..."));

            // WiFi.reconnect() tries to reconnect to the last-used AP
            WiFi.disconnect();
            WiFi.reconnect();
        }
    }
}

/**
 * Send a JSON payload to the API endpoint via HTTP POST.
 * 
 * Sets Content-Type to application/json and uses the configured
 * HTTP_TIMEOUT for the request. Returns true only on 2xx responses.
 * 
 * @param json  Complete JSON string to POST
 * @return true if server responded with HTTP 2xx
 */
bool networkSendData(const String& json) {
    if (!networkIsConnected()) {
        Serial.println(F("[NETWORK] Cannot send — WiFi not connected"));
        return false;
    }

    HTTPClient http;
    http.begin(API_ENDPOINT);
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(HTTP_TIMEOUT);

    // Send HTTP POST request
    int httpCode = http.POST(json);

    if (httpCode > 0) {
        // HTTP response received
        if (httpCode >= 200 && httpCode < 300) {
            Serial.print(F("[NETWORK] POST success (HTTP "));
            Serial.print(httpCode);
            Serial.println(F(")"));
            http.end();
            return true;
        } else {
            // Server responded with non-success code
            Serial.print(F("[NETWORK] POST failed (HTTP "));
            Serial.print(httpCode);
            Serial.print(F("): "));
            Serial.println(http.getString().substring(0, 100));  // Truncate response
        }
    } else {
        // Connection-level error (timeout, DNS failure, etc.)
        Serial.print(F("[NETWORK] POST error: "));
        Serial.println(http.errorToString(httpCode));
    }

    http.end();
    return false;
}

/**
 * Get the device's current IP address as a string.
 */
String networkGetIP() {
    if (networkIsConnected()) {
        return WiFi.localIP().toString();
    }
    return "0.0.0.0";
}

/**
 * Get WiFi signal strength in dBm.
 * Used for visual signal bars on OLED display.
 */
int networkGetRSSI() {
    if (networkIsConnected()) {
        return WiFi.RSSI();
    }
    return -100;  // Return very weak signal if not connected
}
