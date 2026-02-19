/**
 * ============================================================================
 * SAWARI Bus Telemetry Device - Display Handler Implementation
 * ============================================================================
 * 
 * Drives a 1.3" SH1106 128x64 OLED display over I2C using U8g2.
 * 
 * VISUAL ENHANCEMENTS:
 *   1. WiFi Signal Bars   — 4-bar RSSI indicator (like phone signal)
 *   2. GPS Radar Sweep    — Animated rotating radar while searching
 *   3. Speed Bar Graph    — Horizontal progress bar for speed
 *   4. Direction Compass  — 8-direction arrow showing heading
 * 
 * The display operates in full-buffer mode (F_) which uses ~1KB of RAM
 * but allows flicker-free rendering. The ESP32 has plenty of RAM for this.
 * 
 * NOTE: If your 1.3" OLED uses SSD1306 instead of SH1106, change the
 * constructor below to U8G2_SSD1306_128X64_NONAME_F_HW_I2C.
 * ============================================================================
 */

#include "display_handler.h"
#include "config.h"
#include <U8g2lib.h>
#include <Wire.h>
#include <math.h>

// --- OLED display instance ---
static U8G2_SH1106_128X64_NONAME_F_HW_I2C _display(U8G2_R0, /* reset=*/ U8X8_PIN_NONE);

// Reusable line buffer for formatted text
static char _lineBuf[32];

// --- Animation state ---
static unsigned long _lastAnimationTick = 0;
static int _radarAngle = 0;              // Current radar sweep angle (0-359)
static bool _blinkState = false;         // For blinking indicators
static const int ANIMATION_INTERVAL = 100;  // ms between animation frames

// ============================================================================
// VISUAL ENHANCEMENT 1: WiFi Signal Strength Bars
// ============================================================================
/**
 * Draw WiFi signal strength indicator (4 bars like smartphone).
 * 
 * RSSI ranges:
 *   > -50 dBm  = Excellent (4 bars)
 *   > -60 dBm  = Good (3 bars)
 *   > -70 dBm  = Fair (2 bars)
 *   > -80 dBm  = Weak (1 bar)
 *   <= -80 dBm = No signal (0 bars, outline only)
 * 
 * @param x,y     Top-left position
 * @param rssi    WiFi RSSI in dBm
 * @param connected  Whether WiFi is actually connected
 */
static void _drawWiFiBars(int x, int y, int rssi, bool connected) {
    const int barWidth = 3;
    const int barGap = 1;
    const int maxHeight = 10;
    
    // Calculate number of bars based on RSSI
    int bars = 0;
    if (connected) {
        if (rssi > -50) bars = 4;
        else if (rssi > -60) bars = 3;
        else if (rssi > -70) bars = 2;
        else if (rssi > -80) bars = 1;
    }
    
    // Draw 4 bars with increasing heights
    for (int i = 0; i < 4; i++) {
        int barHeight = 3 + (i * 2);  // Heights: 3, 5, 7, 9
        int bx = x + i * (barWidth + barGap);
        int by = y + (maxHeight - barHeight);
        
        if (i < bars) {
            // Filled bar
            _display.drawBox(bx, by, barWidth, barHeight);
        } else {
            // Empty outline
            _display.drawFrame(bx, by, barWidth, barHeight);
        }
    }
    
    // Draw X over bars if disconnected
    if (!connected) {
        _display.drawLine(x, y, x + 14, y + maxHeight);
        _display.drawLine(x + 14, y, x, y + maxHeight);
    }
}

// ============================================================================
// VISUAL ENHANCEMENT 2: GPS Satellite Radar Animation
// ============================================================================
/**
 * Draw animated radar sweep for GPS searching screen.
 * Shows a circular radar with rotating sweep line and satellite dots.
 * 
 * @param cx,cy    Center position
 * @param radius   Radar circle radius
 * @param satellites  Number of satellites (shown as dots)
 */
static void _drawGPSRadar(int cx, int cy, int radius, int satellites) {
    // Draw radar circle outline
    _display.drawCircle(cx, cy, radius);
    
    // Draw crosshairs
    _display.drawLine(cx - radius + 2, cy, cx + radius - 2, cy);
    _display.drawLine(cx, cy - radius + 2, cx, cy + radius - 2);
    
    // Draw rotating sweep line (animated)
    float angleRad = _radarAngle * PI / 180.0;
    int endX = cx + (int)(cos(angleRad) * (radius - 1));
    int endY = cy - (int)(sin(angleRad) * (radius - 1));
    _display.drawLine(cx, cy, endX, endY);
    
    // Draw satellite dots at fixed positions around the radar
    // Position based on satellite count (evenly distributed)
    for (int i = 0; i < min(satellites, 12); i++) {
        float satAngle = (i * 30 + 15) * PI / 180.0;  // 30° apart
        int satR = radius - 4 - (i % 3) * 3;  // Vary distance from center
        int sx = cx + (int)(cos(satAngle) * satR);
        int sy = cy - (int)(sin(satAngle) * satR);
        
        // Blink dots when radar sweep passes them
        int satDegree = (i * 30 + 15) % 360;
        int diff = abs(_radarAngle - satDegree);
        if (diff < 30 || diff > 330) {
            _display.drawDisc(sx, sy, 2);  // Filled when sweep nearby
        } else {
            _display.drawCircle(sx, sy, 1);  // Outline otherwise
        }
    }
}

// ============================================================================
// VISUAL ENHANCEMENT 3: Speed Bar Graph
// ============================================================================
/**
 * Draw horizontal speed bar graph with scale markers.
 * 
 * @param x,y      Top-left position
 * @param width    Total width of bar
 * @param speed    Current speed in km/h
 * @param maxSpeed Maximum speed for full bar (default 80 km/h for bus)
 */
static void _drawSpeedBar(int x, int y, int width, int speed, int maxSpeed) {
    const int height = 6;
    
    // Draw outline frame
    _display.drawFrame(x, y, width, height);
    
    // Calculate fill width
    int fillWidth = (speed * (width - 2)) / maxSpeed;
    if (fillWidth > width - 2) fillWidth = width - 2;
    if (fillWidth < 0) fillWidth = 0;
    
    // Draw filled portion
    if (fillWidth > 0) {
        _display.drawBox(x + 1, y + 1, fillWidth, height - 2);
    }
    
    // Draw tick marks at 25%, 50%, 75%
    for (int i = 1; i < 4; i++) {
        int tickX = x + (width * i) / 4;
        _display.drawPixel(tickX, y);
        _display.drawPixel(tickX, y + height - 1);
    }
}

// ============================================================================
// VISUAL ENHANCEMENT 4: Direction Compass Arrow
// ============================================================================
/**
 * Draw 8-direction compass arrow showing heading.
 * 
 * Directions: N, NE, E, SE, S, SW, W, NW
 * Uses simple arrow polygons for each direction.
 * 
 * @param cx,cy     Center position of compass
 * @param direction Heading in degrees (0 = North, 90 = East, etc.)
 */
static void _drawCompass(int cx, int cy, double direction) {
    const int r = 8;  // Compass radius
    
    // Draw circle outline
    _display.drawCircle(cx, cy, r);
    
    // Calculate arrow endpoint based on direction
    // Direction: 0° = North (up), 90° = East (right)
    float angleRad = (90 - direction) * PI / 180.0;  // Convert to standard math angle
    
    // Arrow tip
    int tipX = cx + (int)(cos(angleRad) * (r - 2));
    int tipY = cy - (int)(sin(angleRad) * (r - 2));
    
    // Arrow base (opposite direction)
    int baseX = cx - (int)(cos(angleRad) * (r - 4));
    int baseY = cy + (int)(sin(angleRad) * (r - 4));
    
    // Arrow wings (perpendicular to direction)
    float perpAngle = angleRad + PI / 2;
    int wingLen = 3;
    int wing1X = baseX + (int)(cos(perpAngle) * wingLen);
    int wing1Y = baseY - (int)(sin(perpAngle) * wingLen);
    int wing2X = baseX - (int)(cos(perpAngle) * wingLen);
    int wing2Y = baseY + (int)(sin(perpAngle) * wingLen);
    
    // Draw arrow
    _display.drawLine(baseX, baseY, tipX, tipY);
    _display.drawLine(wing1X, wing1Y, tipX, tipY);
    _display.drawLine(wing2X, wing2Y, tipX, tipY);
    
    // Draw N marker at top
    _display.drawStr(cx - 2, cy - r - 8, "N");
}

// ============================================================================
// INITIALIZATION
// ============================================================================
/**
 * Initialize the I2C OLED display and show a boot splash.
 */
void displayInit() {
    _display.begin();
    _display.setFont(u8g2_font_6x10_tr);
    _display.setFontRefHeightExtendedText();
    _display.setDrawColor(1);
    _display.setFontPosTop();

    // --- Animated boot splash with logo ---
    _display.clearBuffer();
    
    // Draw decorative frame
    _display.drawFrame(0, 0, 128, 64);
    _display.drawFrame(2, 2, 124, 60);
    
    // Title with larger font
    _display.setFont(u8g2_font_7x14B_tr);
    _display.drawStr(35, 8, "SAWARI");
    
    // Subtitle
    _display.setFont(u8g2_font_6x10_tr);
    _display.drawStr(15, 26, "Bus Telemetry v1.0");
    
    // Decorative bus icon (simple representation)
    int bx = 54, by = 40;
    _display.drawFrame(bx, by, 20, 10);      // Bus body
    _display.drawBox(bx + 2, by + 2, 5, 5);  // Window 1
    _display.drawBox(bx + 9, by + 2, 5, 5);  // Window 2
    _display.drawDisc(bx + 4, by + 10, 2);   // Wheel 1
    _display.drawDisc(bx + 15, by + 10, 2);  // Wheel 2
    
    _display.drawStr(28, 54, "Initializing...");
    _display.sendBuffer();

    Serial.println(F("[DISPLAY] OLED initialized with visual enhancements"));
}

// ============================================================================
// MAIN STATUS SCREEN (Enhanced with visual indicators)
// ============================================================================
/**
 * Show the main telemetry status screen with visual enhancements.
 * 
 * Layout:
 *   [WiFi Bars]  BUS: 1     [Compass]
 *   LAT: 27.7123
 *   LON: 85.3123
 *   SPD: 34 km/h [=====    ]
 *   SAT: 9   HDOP: 0.9
 *   Q:5 pending
 */
void displayShowStatus(const TelemetryData* data, bool wifiOk, int wifiRSSI, int queueCount) {
    _display.clearBuffer();
    _display.setFont(u8g2_font_6x10_tr);

    int y = 0;
    const int lineHeight = 10;

    // --- Row 1: WiFi bars + Bus ID + Compass ---
    _drawWiFiBars(0, y, wifiRSSI, wifiOk);
    
    snprintf(_lineBuf, sizeof(_lineBuf), "BUS:%d", BUS_ID);
    _display.drawStr(20, y, _lineBuf);
    
    // Compass in top-right corner
    _drawCompass(110, 14, data->direction);
    y += lineHeight + 2;

    // --- Row 2: Latitude ---
    char latStr[12];
    dtostrf(data->latitude, 9, 4, latStr);
    snprintf(_lineBuf, sizeof(_lineBuf), "LAT:%s", latStr);
    _display.drawStr(0, y, _lineBuf);
    y += lineHeight;

    // --- Row 3: Longitude ---
    char lonStr[12];
    dtostrf(data->longitude, 9, 4, lonStr);
    snprintf(_lineBuf, sizeof(_lineBuf), "LON:%s", lonStr);
    _display.drawStr(0, y, _lineBuf);
    y += lineHeight;

    // --- Row 4: Speed with bar graph ---
    snprintf(_lineBuf, sizeof(_lineBuf), "SPD:%2d", (int)data->speed);
    _display.drawStr(0, y, _lineBuf);
    _drawSpeedBar(40, y + 1, 50, (int)data->speed, 80);  // 80 km/h max for bus
    _display.drawStr(92, y, "km/h");
    y += lineHeight;

    // --- Row 5: Satellites + HDOP ---
    snprintf(_lineBuf, sizeof(_lineBuf), "SAT:%d", data->satellites);
    _display.drawStr(0, y, _lineBuf);
    
    char hdopStr[6];
    dtostrf(data->hdop, 3, 1, hdopStr);
    snprintf(_lineBuf, sizeof(_lineBuf), "HDOP:%s", hdopStr);
    _display.drawStr(50, y, _lineBuf);
    y += lineHeight;

    // --- Row 6: Queue status (only if items pending) ---
    if (queueCount > 0) {
        snprintf(_lineBuf, sizeof(_lineBuf), ">> %d pending", queueCount);
        // Blink indicator for queued data
        if (_blinkState) {
            _display.drawStr(0, y, _lineBuf);
        }
    } else if (wifiOk) {
        _display.drawStr(0, y, ">> LIVE");
    } else {
        _display.drawStr(0, y, ">> OFFLINE");
    }

    _display.sendBuffer();
}

// ============================================================================
// GPS SEARCHING SCREEN (Enhanced with radar animation)
// ============================================================================
/**
 * Show GPS acquisition screen with animated radar sweep.
 */
void displaySearchingGPS(int satellites, bool wifiOk) {
    _display.clearBuffer();
    _display.setFont(u8g2_font_6x10_tr);

    // Header with Bus ID and WiFi status
    snprintf(_lineBuf, sizeof(_lineBuf), "BUS ID: %d", BUS_ID);
    _display.drawStr(0, 0, _lineBuf);
    
    // WiFi indicator in corner
    _display.drawStr(90, 0, wifiOk ? "[WiFi]" : "[----]");

    // Draw animated radar in center
    _drawGPSRadar(40, 38, 18, satellites);

    // Status text on the right side
    _display.setFont(u8g2_font_6x10_tr);
    _display.drawStr(65, 20, "Searching");
    _display.drawStr(65, 30, "GPS...");
    
    // Satellite count with progress indication
    snprintf(_lineBuf, sizeof(_lineBuf), "Sats: %d/4", satellites);
    _display.drawStr(65, 44, _lineBuf);
    
    // Progress bar for satellite acquisition (need 4 for fix)
    int progress = min(satellites, 4);
    _display.drawFrame(65, 54, 40, 6);
    if (progress > 0) {
        _display.drawBox(66, 55, progress * 9, 4);
    }

    _display.sendBuffer();
}

// ============================================================================
// WIFI SETUP SCREEN (Enhanced with AP icon)
// ============================================================================
/**
 * Show the WiFi setup screen during AP/captive portal mode.
 */
void displayWiFiSetup() {
    _display.clearBuffer();
    
    // Decorative border
    _display.drawFrame(0, 0, 128, 64);
    
    // WiFi setup icon (signal waves)
    int cx = 64, cy = 12;
    _display.drawCircle(cx, cy + 8, 4);      // Router dot
    _display.drawCircle(cx, cy, 10);          // Wave 1
    _display.drawCircle(cx, cy, 16);          // Wave 2
    // Clear bottom half of circles for semicircle effect
    _display.setDrawColor(0);
    _display.drawBox(cx - 20, cy + 2, 40, 20);
    _display.setDrawColor(1);
    _display.drawDisc(cx, cy + 8, 3);        // Router dot filled
    
    _display.setFont(u8g2_font_6x10_tr);
    _display.drawStr(28, 26, "WiFi Setup");
    
    _display.drawStr(10, 38, "Connect to AP:");
    _display.setFont(u8g2_font_7x14B_tr);
    _display.drawStr(15, 48, AP_NAME);
    
    _display.sendBuffer();
}

// ============================================================================
// BOOT PROGRESS SCREEN
// ============================================================================
/**
 * Show boot progress with progress bar.
 */
void displayBootProgress(int progress, const char* status) {
    _display.clearBuffer();
    
    // Title
    _display.setFont(u8g2_font_7x14B_tr);
    _display.drawStr(35, 5, "SAWARI");
    
    // Bus icon
    _display.setFont(u8g2_font_6x10_tr);
    int bx = 54, by = 22;
    _display.drawFrame(bx, by, 20, 10);
    _display.drawBox(bx + 2, by + 2, 5, 5);
    _display.drawBox(bx + 9, by + 2, 5, 5);
    _display.drawDisc(bx + 4, by + 10, 2);
    _display.drawDisc(bx + 15, by + 10, 2);
    
    // Progress bar
    int barX = 14, barY = 40, barW = 100, barH = 10;
    _display.drawFrame(barX, barY, barW, barH);
    int fillW = (progress * (barW - 2)) / 100;
    if (fillW > 0) {
        _display.drawBox(barX + 1, barY + 1, fillW, barH - 2);
    }
    
    // Percentage
    snprintf(_lineBuf, sizeof(_lineBuf), "%d%%", progress);
    _display.drawStr(barX + barW + 4, barY + 1, _lineBuf);
    
    // Status text
    _display.drawStr(10, 54, status);
    
    _display.sendBuffer();
}

// ============================================================================
// ANIMATION TICK
// ============================================================================
/**
 * Update animation state. Call frequently from main loop.
 */
void displayAnimationTick() {
    unsigned long now = millis();
    if (now - _lastAnimationTick >= ANIMATION_INTERVAL) {
        _lastAnimationTick = now;
        
        // Rotate radar sweep (10 degrees per tick)
        _radarAngle = (_radarAngle + 10) % 360;
        
        // Toggle blink state
        _blinkState = !_blinkState;
    }
}
