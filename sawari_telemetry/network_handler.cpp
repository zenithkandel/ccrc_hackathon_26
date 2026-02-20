/**
 * ============================================================================
 * SAWARI Bus Telemetry Device - Network Handler Implementation
 * ============================================================================
 *
 * WiFi connectivity management using WiFiManager library (tzapu/WiFiManager).
 *
 * Features:
 *   - Auto-connect to saved credentials on boot
 *   - On-demand captive portal via button press (non-blocking)
 *   - Auto-close portal when WiFi connects
 *   - 10-second WiFi availability check interval
 *   - Offline mode fallback with automatic reconnection
 *   - HTTP POST telemetry with timeout handling
 * ============================================================================
 */

#include "network_handler.h"
#include "config.h"
#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiManager.h>

// --- WiFiManager instance (persistent for on-demand portal) ---
static WiFiManager _wm;

// --- State ---
static unsigned long _lastReconnectAttempt = 0;
static bool _wasConnected = false;
static bool _portalActive = false;

/**
 * Initialize WiFi using WiFiManager with captive portal support.
 * This call is BLOCKING during AP mode — it waits for the user to
 * configure WiFi via the captive portal, up to AP_TIMEOUT seconds.
 */
bool networkInit() {
    _wm.setConfigPortalTimeout(AP_TIMEOUT);
    _wm.setConnectTimeout(15);
    _wm.setCleanConnect(true);

    Serial.println(F("[NETWORK] Starting WiFiManager..."));
    Serial.print(F("[NETWORK] AP Name: "));
    Serial.println(AP_NAME);

    bool connected = _wm.autoConnect(AP_NAME);

    if (connected) {
        Serial.println(F("[NETWORK] WiFi connected successfully"));
        Serial.print(F("[NETWORK] SSID: "));
        Serial.println(WiFi.SSID());
        Serial.print(F("[NETWORK] IP Address: "));
        Serial.println(WiFi.localIP());
        Serial.print(F("[NETWORK] RSSI: "));
        Serial.print(WiFi.RSSI());
        Serial.println(F(" dBm"));
        _wasConnected = true;
    } else {
        Serial.println(F("[NETWORK] WiFi connection failed / portal timed out"));
        Serial.println(F("[NETWORK] Operating in offline mode"));
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
 * Called every WIFI_CHECK_INTERVAL (10 seconds).
 */
void networkCheckReconnect() {
    bool currentlyConnected = networkIsConnected();

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

    if (!currentlyConnected && !_portalActive) {
        unsigned long now = millis();
        if (now - _lastReconnectAttempt >= WIFI_RECONNECT_INTERVAL) {
            _lastReconnectAttempt = now;
            Serial.println(F("[NETWORK] Attempting WiFi reconnect..."));
            WiFi.disconnect();
            WiFi.reconnect();
        }
    }
}

/**
 * Start the WiFiManager captive portal on demand (non-blocking mode).
 * Called when the user presses the BOOT button.
 */
bool networkStartPortal() {
    if (_portalActive) {
        Serial.println(F("[NETWORK] Portal already active"));
        return true;
    }

    Serial.println(F("[NETWORK] Starting on-demand WiFi portal..."));
    Serial.print(F("[NETWORK] AP Name: "));
    Serial.println(AP_NAME);

    // Use non-blocking portal so GPS and other tasks continue running
    _wm.setConfigPortalBlocking(false);
    _wm.setConfigPortalTimeout(AP_TIMEOUT);

    _wm.startConfigPortal(AP_NAME);
    _portalActive = true;

    Serial.println(F("[NETWORK] Portal started at 192.168.4.1"));
    return true;
}

/**
 * Process portal requests — call in loop while portal is active.
 * Returns true if WiFi got connected through the portal.
 */
bool networkPortalLoop() {
    if (!_portalActive) return false;

    _wm.process();

    // Check if we got connected during portal
    if (networkIsConnected()) {
        Serial.println(F("[NETWORK] WiFi connected via portal!"));
        Serial.print(F("[NETWORK] SSID: "));
        Serial.println(WiFi.SSID());
        Serial.print(F("[NETWORK] IP: "));
        Serial.println(WiFi.localIP());
        _wasConnected = true;
        networkStopPortal();
        return true;
    }

    return false;
}

/**
 * Stop the captive portal.
 */
void networkStopPortal() {
    if (_portalActive) {
        _wm.stopConfigPortal();
        _portalActive = false;
        Serial.println(F("[NETWORK] WiFi portal stopped"));
    }
}

/**
 * Check if the captive portal is currently active.
 */
bool networkIsPortalActive() {
    return _portalActive;
}

/**
 * Send a JSON payload to the API endpoint via HTTP POST.
 * Includes retry logic for transient failures and detailed error logging.
 *
 * @param json  The JSON string to POST
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

    Serial.print(F("[NETWORK] POST → "));
    Serial.println(API_ENDPOINT);
    Serial.print(F("[NETWORK] Payload ("));
    Serial.print(json.length());
    Serial.println(F(" bytes)"));

    int httpCode = http.POST(json);

    if (httpCode > 0) {
        String responseBody = http.getString();

        if (httpCode >= 200 && httpCode < 300) {
            Serial.print(F("[NETWORK] ✓ POST success (HTTP "));
            Serial.print(httpCode);
            Serial.println(F(")"));
            if (responseBody.length() > 0) {
                Serial.print(F("[NETWORK] Response: "));
                Serial.println(responseBody.substring(0, 200));
            }
            http.end();
            return true;
        } else {
            Serial.print(F("[NETWORK] ✗ POST rejected (HTTP "));
            Serial.print(httpCode);
            Serial.println(F(")"));
            Serial.print(F("[NETWORK] Response: "));
            Serial.println(responseBody.substring(0, 200));
        }
    } else {
        Serial.print(F("[NETWORK] ✗ Connection error: "));
        Serial.println(http.errorToString(httpCode));

        // Provide human-readable guidance for common errors
        switch (httpCode) {
            case HTTPC_ERROR_CONNECTION_REFUSED:
                Serial.println(F("[NETWORK]   → Server refused connection. Check URL/port."));
                break;
            case HTTPC_ERROR_SEND_HEADER_FAILED:
            case HTTPC_ERROR_SEND_PAYLOAD_FAILED:
                Serial.println(F("[NETWORK]   → Send failed. WiFi may have dropped."));
                break;
            case HTTPC_ERROR_CONNECTION_LOST:
                Serial.println(F("[NETWORK]   → Connection lost mid-transfer."));
                break;
            case HTTPC_ERROR_READ_TIMEOUT:
                Serial.println(F("[NETWORK]   → Server did not respond in time."));
                break;
            default:
                break;
        }
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
 * Get the portal AP IP address.
 */
String networkGetPortalIP() {
    return "192.168.4.1";
}

/**
 * Get WiFi signal strength in dBm.
 */
int networkGetRSSI() {
    if (networkIsConnected()) {
        return WiFi.RSSI();
    }
    return -100;
}

/**
 * Get the SSID of the currently connected WiFi network.
 */
String networkGetSSID() {
    if (networkIsConnected()) {
        return WiFi.SSID();
    }
    return "";
}
