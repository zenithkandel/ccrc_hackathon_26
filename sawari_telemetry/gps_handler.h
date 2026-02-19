/**
 * ============================================================================
 * SAWARI Bus Telemetry Device - GPS Handler Header
 * ============================================================================
 * Manages NEO-6M GPS module via UART2 using TinyGPSPlus.
 * Provides parsed telemetry data and ISO 8601 timestamp generation.
 * ============================================================================
 */

#ifndef GPS_HANDLER_H
#define GPS_HANDLER_H

#include <Arduino.h>

/**
 * Telemetry data structure holding all GPS-derived values.
 */
struct TelemetryData {
    double  latitude;
    double  longitude;
    double  speed;          // km/h
    double  direction;      // degrees (0-360)
    double  altitude;       // meters
    int     satellites;
    double  hdop;
    char    timestamp[25];  // ISO 8601: "YYYY-MM-DDTHH:MM:SSZ"
};

/**
 * Initialize GPS serial communication on UART2.
 */
void gpsInit();

/**
 * Feed characters from GPS serial to TinyGPSPlus parser.
 * Must be called frequently (every loop iteration) for reliable parsing.
 */
void gpsUpdate();

/**
 * Check whether the GPS has a valid location fix.
 * @return true if location data is valid and updated
 */
bool gpsHasFix();

/**
 * Check whether GPS time/date is valid.
 * @return true if time and date are valid
 */
bool gpsHasTime();

/**
 * Get the current satellite count (valid even without a full fix).
 * @return number of satellites in view
 */
int gpsGetSatellites();

/**
 * Populate a TelemetryData struct with the latest GPS readings.
 * Only call this when gpsHasFix() returns true.
 * @param data pointer to TelemetryData struct to fill
 */
void gpsGetTelemetry(TelemetryData* data);

/**
 * Build a JSON payload string from telemetry data.
 * @param data pointer to populated TelemetryData struct
 * @return JSON string ready for HTTP POST
 */
String gpsFormatPayload(const TelemetryData* data);

#endif // GPS_HANDLER_H
