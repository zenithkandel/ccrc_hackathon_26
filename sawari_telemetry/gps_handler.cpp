/**
 * ============================================================================
 * SAWARI Bus Telemetry Device - GPS Handler Implementation
 * ============================================================================
 * 
 * Interfaces with the NEO-6M GPS module over UART2 (GPIO16 RX, GPIO17 TX).
 * Uses TinyGPSPlus for NMEA sentence parsing to extract:
 *   - Latitude / Longitude
 *   - Speed (km/h)
 *   - Course / Direction (degrees)
 *   - Altitude (meters)
 *   - Satellite count
 *   - HDOP (Horizontal Dilution of Precision)
 *   - UTC Timestamp (ISO 8601)
 * 
 * The gpsUpdate() function must be called every loop iteration to ensure
 * no NMEA sentences are missed from the serial buffer.
 * ============================================================================
 */

#include "gps_handler.h"
#include "config.h"
#include <TinyGPSPlus.h>

// --- GPS parser and serial instances ---
static TinyGPSPlus _gps;
static HardwareSerial _gpsSerial(2);    // UART2

/**
 * Initialize UART2 for GPS communication at 9600 baud.
 * NEO-6M default baud rate is 9600.
 */
void gpsInit() {
    _gpsSerial.begin(GPS_BAUD, SERIAL_8N1, GPS_RX_PIN, GPS_TX_PIN);
    Serial.println(F("[GPS] UART2 initialized at 9600 baud"));
    Serial.print(F("[GPS] RX pin: ")); Serial.print(GPS_RX_PIN);
    Serial.print(F(" | TX pin: ")); Serial.println(GPS_TX_PIN);
}

/**
 * Feed all available bytes from GPS serial into the TinyGPSPlus parser.
 * This is non-blocking and processes whatever data is in the UART buffer.
 */
void gpsUpdate() {
    while (_gpsSerial.available() > 0) {
        char c = _gpsSerial.read();
        _gps.encode(c);
    }
}

/**
 * Check if GPS has a valid location fix.
 * Uses TinyGPSPlus isValid() which verifies the NMEA data integrity.
 */
bool gpsHasFix() {
    return _gps.location.isValid() && _gps.location.isUpdated();
}

/**
 * Check if GPS has valid time and date data.
 */
bool gpsHasTime() {
    return _gps.date.isValid() && _gps.time.isValid();
}

/**
 * Get satellite count regardless of fix status.
 * Useful for displaying acquisition progress.
 */
int gpsGetSatellites() {
    return _gps.satellites.isValid() ? (int)_gps.satellites.value() : 0;
}

/**
 * Populate a TelemetryData struct with current GPS readings.
 * 
 * IMPORTANT: Only call this when gpsHasFix() returns true,
 * otherwise the data will contain default/stale values.
 */
void gpsGetTelemetry(TelemetryData* data) {
    // Latitude and longitude in decimal degrees
    data->latitude  = _gps.location.lat();
    data->longitude = _gps.location.lng();

    // Speed in km/h (TinyGPSPlus provides this directly)
    data->speed = _gps.speed.isValid() ? _gps.speed.kmph() : 0.0;

    // Course/direction in degrees (0 = North, 90 = East, etc.)
    data->direction = _gps.course.isValid() ? _gps.course.deg() : 0.0;

    // Altitude in meters above mean sea level
    data->altitude = _gps.altitude.isValid() ? _gps.altitude.meters() : 0.0;

    // Number of satellites used in fix
    data->satellites = gpsGetSatellites();

    // HDOP - lower is better (< 1.0 = excellent, 1-2 = good)
    data->hdop = _gps.hdop.isValid() ? (_gps.hdop.hdop()) : 99.9;

    // Format timestamp as ISO 8601 UTC string
    if (gpsHasTime()) {
        snprintf(data->timestamp, sizeof(data->timestamp),
                 "%04d-%02d-%02dT%02d:%02d:%02dZ",
                 _gps.date.year(),
                 _gps.date.month(),
                 _gps.date.day(),
                 _gps.time.hour(),
                 _gps.time.minute(),
                 _gps.time.second());
    } else {
        // Fallback if GPS time not yet acquired
        strncpy(data->timestamp, "1970-01-01T00:00:00Z", sizeof(data->timestamp));
    }
}

/**
 * Build a JSON payload string from telemetry data.
 * Uses snprintf for efficient string formatting without ArduinoJson dependency.
 * 
 * Output format matches the API specification:
 * {
 *   "bus_id": 1,
 *   "latitude": 27.712345,
 *   "longitude": 85.312345,
 *   "speed": 34.5,
 *   "direction": 182.4,
 *   "altitude": 1350.2,
 *   "satellites": 9,
 *   "hdop": 0.9,
 *   "timestamp": "2026-02-19T10:15:23Z"
 * }
 */
String gpsFormatPayload(const TelemetryData* data) {
    char buffer[384];
    snprintf(buffer, sizeof(buffer),
        "{"
        "\"bus_id\":%d,"
        "\"latitude\":%.6f,"
        "\"longitude\":%.6f,"
        "\"speed\":%.1f,"
        "\"direction\":%.1f,"
        "\"altitude\":%.1f,"
        "\"satellites\":%d,"
        "\"hdop\":%.1f,"
        "\"timestamp\":\"%s\""
        "}",
        BUS_ID,
        data->latitude,
        data->longitude,
        data->speed,
        data->direction,
        data->altitude,
        data->satellites,
        data->hdop,
        data->timestamp
    );
    return String(buffer);
}
