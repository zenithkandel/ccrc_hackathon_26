# GPS Telemetry System Documentation

## Table of Contents
1. [GPS Module Overview](#gps-module-overview)
2. [NMEA Sentences & Data Flow](#nmea-sentences--data-flow)
3. [Telemetry Parameters Explained](#telemetry-parameters-explained)
4. [Data Processing Pipeline](#data-processing-pipeline)
5. [JSON Payload Structure](#json-payload-structure)
6. [Quality Indicators & Troubleshooting](#quality-indicators--troubleshooting)

---

## GPS Module Overview

### Hardware: NEO-6M GPS Modulei
- **Manufacturer**: u-blox
- **Chipset**: u-blox 6 positioning engine
- **Communication**: UART serial (TTL level, 3.3V/5V compatible)
- **Default Baud Rate**: 9600 bps (8N1: 8 data bits, no parity, 1 stop bit)
- **Update Rate**: 1 Hz (default) — updates position data once per second
- **Cold Start Time**: ~27 seconds (no stored ephemeris data)
- **Warm Start Time**: ~27 seconds
- **Hot Start Time**: ~1 second (almanac & ephemeris still valid)
- **Position Accuracy**: 2.5 meters CEP (Circular Error Probable)
- **Velocity Accuracy**: 0.1 m/s
- **Channels**: 50 (tracking multiple satellites simultaneously)
- **Supported Systems**: GPS (no GLONASS on NEO-6M; use NEO-M8N for multi-GNSS)

### Wiring on ESP32
```
NEO-6M GPS        ESP32 Dev Module
-----------       ----------------
VCC        →      5V or 3.3V (check module specs)
GND        →      GND
TX (GPS)   →      GPIO16 (RX2, UART2)
RX (GPS)   →      GPIO17 (TX2, UART2)
```

**Important**: The GPS module's TX pin connects to ESP32's RX pin, and vice versa.

---

## NMEA Sentences & Data Flow

### What is NMEA?
NMEA 0183 is a standard communication protocol used by GPS receivers to transmit position, velocity, and time data as ASCII text sentences.

### Example NMEA Sentences from NEO-6M

The NEO-6M outputs multiple NMEA sentence types every second:

#### 1. **$GPGGA** — Global Positioning System Fix Data
```
$GPGGA,123519,4807.038,N,01131.000,E,1,08,0.9,545.4,M,46.9,M,,*47
```
**Fields**:
- `123519` = UTC Time (12:35:19)
- `4807.038,N` = Latitude 48°07.038' North
- `01131.000,E` = Longitude 11°31.000' East
- `1` = Fix Quality (0=invalid, 1=GPS fix, 2=DGPS fix)
- `08` = Number of satellites being tracked
- `0.9` = **HDOP** (Horizontal Dilution of Precision)
- `545.4,M` = Altitude above mean sea level (meters)
- `46.9,M` = Height of geoid above WGS84 ellipsoid
- `*47` = Checksum

#### 2. **$GPRMC** — Recommended Minimum Specific GNSS Data
```
$GPRMC,123519,A,4807.038,N,01131.000,E,022.4,084.4,230394,003.1,W*6A
```
**Fields**:
- `123519` = UTC Time (12:35:19)
- `A` = Status (A=active/valid, V=void/invalid)
- `4807.038,N` = Latitude
- `01131.000,E` = Longitude
- `022.4` = **Speed over ground (knots)**
- `084.4` = **Track angle / Course (degrees)**
- `230394` = Date (23/03/1994)
- `003.1,W` = Magnetic variation
- `*6A` = Checksum

#### 3. **$GPGSA** — GNSS DOP and Active Satellites
```
$GPGSA,A,3,04,05,,09,12,,,24,,,,,2.5,1.3,2.1*39
```
**Fields**:
- `A` = Auto/manual 2D/3D mode selection
- `3` = Fix type (1=no fix, 2=2D, 3=3D)
- `04,05,...,24` = PRNs (satellite IDs) used in position solution
- `2.5` = PDOP (Position Dilution of Precision)
- `1.3` = **HDOP** (Horizontal Dilution of Precision)
- `2.1` = VDOP (Vertical Dilution of Precision)

#### 4. **$GPGSV** — GNSS Satellites in View
```
$GPGSV,2,1,08,01,40,083,46,02,17,308,41,12,07,344,39,14,22,228,45*75
```
Shows details of satellites in view (azimuth, elevation, signal strength).

### How TinyGPSPlus Parses NMEA

The `TinyGPSPlus` library continuously reads characters from the GPS serial stream and reconstructs complete NMEA sentences. When a valid sentence is detected:

1. **Character-by-character parsing**: Each character from `Serial2.read()` is fed to `_gps.encode(c)`
2. **Sentence validation**: Checksum is verified to ensure data integrity
3. **Field extraction**: Latitude, longitude, speed, course, altitude, etc. are extracted and stored
4. **Freshness tracking**: Each field has an "age" (milliseconds since last update) and "isValid" flag

---

## Telemetry Parameters Explained

### 1. **Latitude** (`double`, decimal degrees)
- **Range**: -90° (South Pole) to +90° (North Pole)
- **Precision**: 6 decimal places ≈ 0.11 meter accuracy
- **Example**: `27.712345` = 27.712345° North
- **Source NMEA**: $GPGGA, $GPRMC
- **TinyGPSPlus**: `_gps.location.lat()`

### 2. **Longitude** (`double`, decimal degrees)
- **Range**: -180° (West) to +180° (East)
- **Precision**: 6 decimal places ≈ 0.11 meter accuracy at equator
- **Example**: `85.312345` = 85.312345° East
- **Source NMEA**: $GPGGA, $GPRMC
- **TinyGPSPlus**: `_gps.location.lng()`

### 3. **Speed** (`double`, km/h)
- **Definition**: Speed over ground (horizontal velocity)
- **Range**: 0 to ~1800 km/h (theoretical GPS limit)
- **Accuracy**: ±0.1 m/s (±0.36 km/h) for NEO-6M
- **Example**: `34.5` = 34.5 km/h
- **Source NMEA**: $GPRMC (originally in knots, converted by TinyGPSPlus)
- **TinyGPSPlus**: `_gps.speed.kmph()`
- **Note**: Requires movement; stationary GPS may show noise (0-2 km/h)

### 4. **Direction / Course** (`double`, degrees)
- **Definition**: Track angle / heading relative to true north
- **Range**: 0° to 360° (0° = North, 90° = East, 180° = South, 270° = West)
- **Accuracy**: ±0.5° when moving at >5 km/h
- **Example**: `182.4` = 182.4° (slightly south of due south)
- **Source NMEA**: $GPRMC
- **TinyGPSPlus**: `_gps.course.deg()`
- **Note**: Only valid when moving; unreliable when stationary

### 5. **Altitude** (`double`, meters)
- **Definition**: Height above mean sea level (MSL), not WGS84 ellipsoid
- **Range**: -600m (Dead Sea) to +8848m (Mt. Everest), theoretically -500 to +18,000m for NEO-6M
- **Accuracy**: ±15 meters vertical for NEO-6M
- **Example**: `1350.2` = 1350.2 meters above sea level
- **Source NMEA**: $GPGGA
- **TinyGPSPlus**: `_gps.altitude.meters()`
- **Note**: Vertical accuracy is ~1.5x worse than horizontal

### 6. **Satellites** (`int`, count)
- **Definition**: Number of satellites actively used in the position fix
- **Range**: 0 to 12+ (NEO-6M can track up to 50 channels but typically uses 4-12 for fix)
- **Minimum for Fix**: 
  - **3 satellites**: 2D fix (lat/lon only, no altitude)
  - **4 satellites**: 3D fix (lat/lon/altitude)
- **Typical Values**:
  - `0-3`: No fix or poor fix
  - `4-6`: Marginal fix (urban canyon, indoors)
  - `7-9`: Good fix (suburban, partial sky view)
  - `10+`: Excellent fix (open sky)
- **Example**: `9` = 9 satellites in use
- **Source NMEA**: $GPGGA, $GPGSA
- **TinyGPSPlus**: `_gps.satellites.value()`

### 7. **HDOP** (`double`, dimensionless)
- **Full Name**: Horizontal Dilution of Precision
- **Definition**: A measure of the **geometric quality** of the GPS satellite constellation
- **How it Works**: 
  - HDOP measures how "spread out" the satellites are in the sky
  - Lower values = better satellite geometry = more accurate position
  - Satellites clustered together = high HDOP = poor accuracy
  - Satellites evenly distributed across sky = low HDOP = high accuracy

- **Quality Scale**:
  ```
  HDOP Value    Rating          Typical Accuracy
  ----------    ------          ----------------
  < 1.0         Excellent       < 1 meter
  1.0 - 2.0     Good            1-2 meters
  2.0 - 5.0     Moderate        2-5 meters
  5.0 - 10.0    Fair            5-10 meters
  10.0 - 20.0   Poor            10-20 meters
  > 20.0        Very Poor       > 20 meters (unreliable)
  99.9          Invalid         No fix or bad data
  ```

- **Example**: `0.9` = Excellent satellite geometry, sub-meter accuracy expected
- **Source NMEA**: $GPGGA, $GPGSA
- **TinyGPSPlus**: `_gps.hdop.hdop()`

- **Related DOP Metrics**:
  - **PDOP** (Position DOP): 3D position error (horizontal + vertical)
  - **VDOP** (Vertical DOP): Vertical/altitude error only
  - **Relationship**: `PDOP² = HDOP² + VDOP²`

- **Why HDOP Matters**:
  - Used to **filter unreliable GPS data** — if HDOP > 5, the position may be inaccurate
  - Helps detect when GPS is struggling (e.g., urban canyon, partial sky view)
  - Can trigger warnings in the application or delay data submission until HDOP improves

### 8. **Timestamp** (`char[25]`, ISO 8601 UTC string)
- **Format**: `YYYY-MM-DDTHH:MM:SSZ`
- **Example**: `2026-02-19T10:15:23Z`
- **Breakdown**:
  - `2026-02-19` = Date (February 19, 2026)
  - `T` = ISO 8601 separator between date and time
  - `10:15:23` = UTC time (not local time)
  - `Z` = Zulu time zone (UTC+0)
- **Source NMEA**: $GPRMC, $GPGGA (separate date and time fields)
- **TinyGPSPlus**: 
  - Date: `_gps.date.year()`, `_gps.date.month()`, `_gps.date.day()`
  - Time: `_gps.time.hour()`, `_gps.time.minute()`, `_gps.time.second()`
- **Fallback**: If GPS doesn't have time yet (no satellite lock), defaults to `1970-01-01T00:00:00Z` (Unix epoch)

---

## Data Processing Pipeline

### Step-by-Step Flow

```
┌──────────────────────────────────────────────────────────────────┐
│  NEO-6M GPS Module                                               │
│  • Receives signals from 4-12 satellites                         │
│  • Calculates position, velocity, time                           │
│  • Outputs NMEA sentences at 9600 baud, 1 Hz update rate         │
└────────────────────────────┬─────────────────────────────────────┘
                             │ UART2 (GPIO16/17)
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│  ESP32 - gpsUpdate() Function                                    │
│  • Reads bytes from Serial2 buffer (non-blocking)                │
│  • Feeds each character to TinyGPSPlus: _gps.encode(c)           │
│  • TinyGPSPlus parses NMEA sentences incrementally               │
└────────────────────────────┬─────────────────────────────────────┘
                             │ Every 2 seconds (SEND_INTERVAL)
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│  ESP32 - Main Loop Check: gpsHasFix()                            │
│  • Validates: _gps.location.isValid() && age() < 5000ms          │
│  • If TRUE: proceed to telemetry extraction                      │
│  • If FALSE: display "Searching GPS..." screen                   │
└────────────────────────────┬─────────────────────────────────────┘
                             │ FIX AVAILABLE
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│  ESP32 - gpsGetTelemetry(&telemetry)                             │
│  • Extracts decimal lat/lon from _gps.location                   │
│  • Extracts speed (km/h) from _gps.speed                         │
│  • Extracts direction (degrees) from _gps.course                 │
│  • Extracts altitude (m) from _gps.altitude                      │
│  • Extracts satellite count from _gps.satellites                 │
│  • Extracts HDOP from _gps.hdop                                  │
│  • Formats UTC timestamp from _gps.date + _gps.time              │
│  • Stores in TelemetryData struct                                │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│  ESP32 - gpsFormatPayload(&telemetry)                            │
│  • Builds JSON string using snprintf():                          │
│    {                                                              │
│      "data": {                                                    │
│        "bus_id": 1,                                               │
│        "latitude": 27.712345,                                     │
│        "longitude": 85.312345,                                    │
│        "speed": 34.5,                                             │
│        "direction": 182.4,                                        │
│        "altitude": 1350.2,                                        │
│        "satellites": 9,                                           │
│        "hdop": 0.9,                                               │
│        "timestamp": "2026-02-19T10:15:23Z"                        │
│      }                                                            │
│    }                                                              │
│  • Buffer size: 400 bytes (384 data + 16 margin)                 │
└────────────────────────────┬─────────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│  ESP32 - Network Send Decision                                   │
│  ┌──────────────────┐              ┌──────────────────┐          │
│  │ WiFi Connected?  │─── YES ─────→│ networkSendData()│          │
│  └────────┬─────────┘              └────────┬─────────┘          │
│           │ NO                               │                    │
│           ▼                                  ▼                    │
│  ┌──────────────────┐              ┌──────────────────┐          │
│  │storageEnqueue()  │              │  HTTP POST       │          │
│  │(offline queue)   │              │  to API endpoint │          │
│  └──────────────────┘              └────────┬─────────┘          │
│           │                                  │                    │
│           │  ┌─ Every 15s or reconnect       │                    │
│           │  │                                ▼                    │
│           ▼  ▼                       ┌──────────────────┐          │
│  ┌──────────────────┐               │ HTTP 200-299?    │          │
│  │ storageFlush()   │◄── SUCCESS ───┤                  │          │
│  │ (auto-retry)     │               │ LED blink        │          │
│  └──────────────────┘               └────────┬─────────┘          │
│                                               │ FAIL (HTTP 400+)  │
│                                               ▼                    │
│                                       ┌──────────────────┐          │
│                                       │ Back to queue,   │          │
│                                       │ retry later      │          │
│                                       └──────────────────┘          │
└──────────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│  Web Server API: http://zenithkandel.com.np/test%20api/api.php  │
│  • Receives POST with Content-Type: application/json            │
│  • Validates "data" field exists                                 │
│  • Stores telemetry in database                                  │
│  • Returns {"status":"success"} or error message                 │
└──────────────────────────────────────────────────────────────────┘
```

---

## JSON Payload Structure

### Current Implementation (v2.0.0)

```json
{
  "data": {
    "bus_id": 1,
    "latitude": 27.712345,
    "longitude": 85.312345,
    "speed": 34.5,
    "direction": 182.4,
    "altitude": 1350.2,
    "satellites": 9,
    "hdop": 0.9,
    "timestamp": "2026-02-19T10:15:23Z"
  }
}
```

### Field Details

| Field       | Type   | Unit         | Description                                      | Example            |
|-------------|--------|--------------|--------------------------------------------------|--------------------|
| `bus_id`    | int    | -            | Unique bus identifier (from `BUS_ID` config)     | `1`                |
| `latitude`  | float  | degrees      | Decimal latitude, 6 decimal places               | `27.712345`        |
| `longitude` | float  | degrees      | Decimal longitude, 6 decimal places              | `85.312345`        |
| `speed`     | float  | km/h         | Speed over ground, 1 decimal place               | `34.5`             |
| `direction` | float  | degrees      | Course/heading relative to true north            | `182.4`            |
| `altitude`  | float  | meters       | Height above mean sea level, 1 decimal place     | `1350.2`           |
| `satellites`| int    | count        | Number of satellites used in position fix        | `9`                |
| `hdop`      | float  | -            | Horizontal dilution of precision (quality)       | `0.9`              |
| `timestamp` | string | ISO 8601 UTC | Date/time from GPS in `YYYY-MM-DDTHH:MM:SSZ`     | `2026-02-19T10:15:23Z` |

### Payload Size
- **Typical size**: 180-200 bytes
- **Buffer allocation**: 400 bytes (safe margin for long values)
- **Network overhead**: ~350 bytes total (HTTP headers + JSON payload)

---

## Quality Indicators & Troubleshooting

### GPS Fix Quality Diagnosis

#### Scenario 1: No Satellites (`satellites = 0`)
**Symptoms**:
- OLED shows "Searching GPS..." indefinitely
- `gpsHasFix()` returns `false`
- LED_GPS remains OFF

**Possible Causes**:
1. **Antenna obstructed**: GPS module indoors, under metal roof, or in a vehicle with metallized windshield
2. **Wiring issue**: TX/RX pins swapped, loose connection, or wrong GPIO assignment
3. **Power issue**: Insufficient current (GPS draws ~40-60mA), voltage drop
4. **Cold start**: First use or battery backup failed — can take up to 27 seconds

**Solutions**:
- Move to open sky (balcony, rooftop, street)
- Verify wiring: GPS TX → ESP32 GPIO16, GPS RX → ESP32 GPIO17
- Check Serial Monitor for `[GPS] UART2 initialized` message
- Wait 60-120 seconds for cold start acquisition

---

#### Scenario 2: Low Satellite Count (`satellites = 3-5`)
**Symptoms**:
- GPS fix available but intermittent
- High HDOP (5-10+)
- Position accuracy ±10-20 meters
- OLED shows fix but "Q: POOR"

**Possible Causes**:
1. **Partial sky view**: Urban canyon, tree cover, building obstruction
2. **Weak signal**: Antenna orientation, reflection/multipath errors
3. **Satellite geometry**: Satellites clustered in one part of sky

**Solutions**:
- Reposition vehicle for better sky visibility
- Wait for satellite constellation to improve (5-10 minutes)
- Check HDOP value — if > 5.0, consider delaying transmission

---

#### Scenario 3: Good Satellite Count but High HDOP (`satellites = 8+, hdop > 5.0`)
**Symptoms**:
- Many satellites visible but poor accuracy
- Position "jumps" by 10-20 meters between readings
- Direction/course unstable

**Possible Causes**:
1. **Poor satellite geometry**: All satellites in one quadrant (e.g., all to the south)
2. **Multipath interference**: Signals bouncing off buildings/ground
3. **Ionospheric disturbance**: Solar activity, atmospheric conditions

**Solutions**:
- Wait 5-10 minutes for satellite constellation to shift
- Move away from tall buildings or reflective surfaces
- Implement HDOP filtering in code:
  ```cpp
  if (telemetry.hdop > 5.0) {
      Serial.println("[GPS] HDOP too high, skipping transmission");
      return;
  }
  ```

---

#### Scenario 4: Speed Noise When Stationary
**Symptoms**:
- Vehicle stationary but speed shows 1-3 km/h
- Direction changes randomly

**Explanation**:
- GPS position has ±2.5m accuracy
- When stationary, position "drifts" in a small circle
- Speed is calculated from position change over time → noise registers as movement

**Solutions**:
- Implement speed threshold:
  ```cpp
  if (telemetry.speed < 3.0) {
      telemetry.speed = 0.0;  // Zero out noise
      telemetry.direction = 0.0;  // Direction invalid when stationary
  }
  ```

---

### NMEA Sentence Debugging

If you need to see raw NMEA data, add this to `gpsUpdate()`:

```cpp
void gpsUpdate() {
    while (_gpsSerial.available() > 0) {
        char c = _gpsSerial.read();
        Serial.write(c);  // Echo raw NMEA to Serial Monitor
        _gps.encode(c);
    }
}
```

**Expected output**:
```
$GPGGA,123519,4807.038,N,01131.000,E,1,08,0.9,545.4,M,46.9,M,,*47
$GPGSA,A,3,04,05,,09,12,,,24,,,,,2.5,1.3,2.1*39
$GPRMC,123519,A,4807.038,N,01131.000,E,022.4,084.4,230394,003.1,W*6A
```

---

## GPS Data Freshness & Validity

### `gpsHasFix()` Implementation
```cpp
bool gpsHasFix() {
    return _gps.location.isValid() && _gps.location.age() < 5000;
}
```

**Logic**:
- **`isValid()`**: Checks if a valid NMEA sentence with position was received
- **`age() < 5000`**: Ensures data is less than 5 seconds old (fresh)

**Why use `age()` instead of `isUpdated()`?**
- `isUpdated()` returns `true` only once per GPS update (auto-clears after first read)
- Since the loop runs ~100x per second but GPS updates at 1 Hz, using `isUpdated()` would cause the fix flag to be `true` for only ~10ms per second
- The 2-second send interval would frequently miss this narrow window
- Using `age() < 5000` keeps the fix flag `true` as long as data is recent

---

## API Error Handling

### Common HTTP Response Codes

| Code | Meaning               | Cause                                      | Solution                          |
|------|-----------------------|--------------------------------------------|-----------------------------------|
| 200  | Success               | Data accepted                              | None (normal operation)           |
| 400  | Bad Request           | Missing "data" field, malformed JSON       | Check `gpsFormatPayload()` format |
| 401  | Unauthorized          | API key missing/invalid                    | Add authentication if required    |
| 404  | Not Found             | Wrong API endpoint URL                     | Verify `API_ENDPOINT` in config.h |
| 500  | Internal Server Error | Database error, server crash               | Check server logs, retry later    |
| 503  | Service Unavailable   | Server overloaded, maintenance             | Implement exponential backoff     |
| -1   | Connection Refused    | Server offline, firewall blocking          | Check network, ping server        |
| -11  | Read Timeout          | Server not responding within 5 seconds     | Increase `HTTP_TIMEOUT`           |

### Example Serial Output (Success)
```
[NETWORK] POST → http://zenithkandel.com.np/test%20api/api.php
[NETWORK] Payload (185 bytes)
[NETWORK] ✓ POST success (HTTP 200)
[NETWORK] Response: {"status":"success","id":12345}
```

### Example Serial Output (Failure)
```
[NETWORK] POST → http://zenithkandel.com.np/test%20api/api.php
[NETWORK] Payload (185 bytes)
[NETWORK] ✗ POST rejected (HTTP 400)
[NETWORK] Response: {"status":"error","message":"Missing 'data' field"}
[STORAGE] Flush partial: sent=0, remaining=36
```

---

## Performance Characteristics

### Timing Analysis

| Task                     | Interval      | Duration        | Notes                                      |
|--------------------------|---------------|-----------------|--------------------------------------------|
| GPS NMEA output          | 1 Hz (1000ms) | ~200ms burst    | Complete set of NMEA sentences             |
| `gpsUpdate()` parsing    | Continuous    | <1ms per char   | Non-blocking, processes serial buffer      |
| `gpsHasFix()` check      | Every loop    | <0.1ms          | Simple boolean check                       |
| Telemetry extraction     | 2000ms        | ~2ms            | Reads all GPS parameters into struct       |
| JSON formatting          | 2000ms        | ~1ms            | `snprintf()` into 400-byte buffer          |
| HTTP POST                | 2000ms        | 100-500ms       | Network round-trip to server               |
| LittleFS queue write     | 2000ms        | 5-20ms          | Flash write (when offline)                 |
| OLED display refresh     | 500ms         | 15-30ms         | Full-buffer mode, I2C @400kHz              |

### Memory Usage

| Component          | RAM Usage      | Flash Usage    |
|--------------------|----------------|----------------|
| TinyGPSPlus        | ~600 bytes     | ~10 KB         |
| HardwareSerial     | 256 bytes      | -              |
| TelemetryData      | 80 bytes       | -              |
| JSON buffer        | 400 bytes      | -              |
| LittleFS queue     | ~500 bytes     | 100 KB (max)   |
| **Total (approx)** | **~8 KB**      | **~200 KB**    |

---

## References & Further Reading

1. **NEO-6M Datasheet**: [u-blox NEO-6 Series](https://www.u-blox.com/en/docs/UBX-13003221)
2. **NMEA 0183 Protocol**: [NMEA Sentence Reference](https://www.nmea.org/Assets/20190303%20nmea%200183%20sentences%20not%20recommended%20for%20new%20designs.pdf)
3. **TinyGPSPlus Library**: [GitHub - mikalhart/TinyGPSPlus](https://github.com/mikalhart/TinyGPSPlus)
4. **HDOP Explanation**: [Dilution of Precision (Wikipedia)](https://en.wikipedia.org/wiki/Dilution_of_precision_(navigation))
5. **ISO 8601 Timestamp**: [ISO 8601 Standard](https://en.wikipedia.org/wiki/ISO_8601)
6. **GPS Accuracy Factors**: [GPS.gov - Accuracy](https://www.gps.gov/systems/gps/performance/accuracy/)

---

## Revision History

| Version | Date       | Changes                                             |
|---------|------------|-----------------------------------------------------|
| 1.0     | 2026-02-19 | Initial documentation - GPS telemetry system v2.0.0 |

---

**SAWARI Bus Telemetry Device**  
ESP32 + NEO-6M GPS + SH1106 OLED  
Firmware Version: 2.0.0
