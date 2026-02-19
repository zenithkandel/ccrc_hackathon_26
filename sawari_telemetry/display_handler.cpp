/**
 * ============================================================================
 * SAWARI Bus Telemetry Device - Display Handler Implementation
 * ============================================================================
 *
 * Drives a 1.3" SH1106 128x64 OLED display over I2C using U8g2.
 *
 * Screens implemented:
 *   1. Boot splash with progress bar and connection status
 *   2. WiFi portal active screen (AP name, portal IP)
 *   3. WiFi connected confirmation (SSID, IP, RSSI bars)
 *   4. GPS searching with animated radar
 *   5. Main telemetry: Lat, Lon, Speed, Direction, Sats, HDOP, WiFi info
 *   6. Offline mode indicator with queue depth and retry countdown
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
static char _lineBuf[40];

// --- Animation state ---
static unsigned long _lastAnimationTick = 0;
static int _radarAngle = 0;
static bool _blinkState = false;
static int _spinnerFrame = 0;
static const int ANIMATION_INTERVAL = 100;

// ============================================================================
// HELPER: WiFi Signal Strength Bars
// ============================================================================
static void _drawWiFiBars(int x, int y, int rssi, bool connected) {
    const int barWidth = 3;
    const int barGap = 1;
    const int maxHeight = 10;

    int bars = 0;
    if (connected) {
        if (rssi > -50) bars = 4;
        else if (rssi > -60) bars = 3;
        else if (rssi > -70) bars = 2;
        else if (rssi > -80) bars = 1;
    }

    for (int i = 0; i < 4; i++) {
        int barHeight = 3 + (i * 2);
        int bx = x + i * (barWidth + barGap);
        int by = y + (maxHeight - barHeight);
        if (i < bars) {
            _display.drawBox(bx, by, barWidth, barHeight);
        } else {
            _display.drawFrame(bx, by, barWidth, barHeight);
        }
    }

    if (!connected) {
        _display.drawLine(x, y, x + 14, y + maxHeight);
        _display.drawLine(x + 14, y, x, y + maxHeight);
    }
}

// ============================================================================
// HELPER: GPS Satellite Radar Animation
// ============================================================================
static void _drawGPSRadar(int cx, int cy, int radius, int satellites) {
    _display.drawCircle(cx, cy, radius);
    _display.drawLine(cx - radius + 2, cy, cx + radius - 2, cy);
    _display.drawLine(cx, cy - radius + 2, cx, cy + radius - 2);

    float angleRad = _radarAngle * PI / 180.0;
    int endX = cx + (int)(cos(angleRad) * (radius - 1));
    int endY = cy - (int)(sin(angleRad) * (radius - 1));
    _display.drawLine(cx, cy, endX, endY);

    for (int i = 0; i < min(satellites, 12); i++) {
        float satAngle = (i * 30 + 15) * PI / 180.0;
        int satR = radius - 4 - (i % 3) * 3;
        int sx = cx + (int)(cos(satAngle) * satR);
        int sy = cy - (int)(sin(satAngle) * satR);
        int satDegree = (i * 30 + 15) % 360;
        int diff = abs(_radarAngle - satDegree);
        if (diff < 30 || diff > 330) {
            _display.drawDisc(sx, sy, 2);
        } else {
            _display.drawCircle(sx, sy, 1);
        }
    }
}

// ============================================================================
// HELPER: Speed Bar Graph
// ============================================================================
static void _drawSpeedBar(int x, int y, int width, int speed, int maxSpeed) {
    const int height = 6;
    _display.drawFrame(x, y, width, height);
    int fillWidth = (speed * (width - 2)) / maxSpeed;
    if (fillWidth > width - 2) fillWidth = width - 2;
    if (fillWidth < 0) fillWidth = 0;
    if (fillWidth > 0) {
        _display.drawBox(x + 1, y + 1, fillWidth, height - 2);
    }
    for (int i = 1; i < 4; i++) {
        int tickX = x + (width * i) / 4;
        _display.drawPixel(tickX, y);
        _display.drawPixel(tickX, y + height - 1);
    }
}

// ============================================================================
// HELPER: draw a small spinner (rotating line segments)
// ============================================================================
static void _drawSpinner(int cx, int cy, int r) {
    const int segments = 8;
    for (int i = 0; i < segments; i++) {
        float angle = (i * 360.0 / segments + _spinnerFrame * 45) * PI / 180.0;
        int x1 = cx + (int)(cos(angle) * (r - 3));
        int y1 = cy - (int)(sin(angle) * (r - 3));
        int x2 = cx + (int)(cos(angle) * r);
        int y2 = cy - (int)(sin(angle) * r);
        if (i == 0) {
            _display.drawLine(x1, y1, x2, y2);
            _display.drawLine(x1 + 1, y1, x2 + 1, y2);
        } else if (i < 3) {
            _display.drawLine(x1, y1, x2, y2);
        } else {
            _display.drawPixel(x2, y2);
        }
    }
}

// ============================================================================
// HELPER: Direction Compass Arrow (small, no "N" label)
// ============================================================================
static void _drawCompassSmall(int cx, int cy, int r, double direction) {
    _display.drawCircle(cx, cy, r);
    float angleRad = (90 - direction) * PI / 180.0;
    int tipX = cx + (int)(cos(angleRad) * (r - 1));
    int tipY = cy - (int)(sin(angleRad) * (r - 1));
    int baseX = cx - (int)(cos(angleRad) * (r - 3));
    int baseY = cy + (int)(sin(angleRad) * (r - 3));
    float perpAngle = angleRad + PI / 2;
    int wing1X = baseX + (int)(cos(perpAngle) * 2);
    int wing1Y = baseY - (int)(sin(perpAngle) * 2);
    int wing2X = baseX - (int)(cos(perpAngle) * 2);
    int wing2Y = baseY + (int)(sin(perpAngle) * 2);
    _display.drawLine(baseX, baseY, tipX, tipY);
    _display.drawLine(wing1X, wing1Y, tipX, tipY);
    _display.drawLine(wing2X, wing2Y, tipX, tipY);
}

// ============================================================================
// 1. BOOT SPLASH — displayInit()
// ============================================================================
//
//  128x64 layout:
//  ┌──────────────────────────┐  y=0   outer frame
//  │┌────────────────────────┐│  y=2   inner frame
//  ││     SAWARI  (7x14B)    ││  y=6   42px centered at x=43
//  ││  Bus Telemetry v1.0    ││  y=22  108px centered at x=10
//  ││      [Bus Icon]        ││  y=36  body 20x10 + wheels
//  ││     Booting...         ││  y=49  60px centered at x=34
//  │└────────────────────────┘│  y=61  inner frame bottom
//  └──────────────────────────┘  y=63  outer frame bottom
//
void displayInit() {
    _display.begin();
    _display.setFont(u8g2_font_6x10_tr);
    _display.setFontRefHeightExtendedText();
    _display.setDrawColor(1);
    _display.setFontPosTop();

    _display.clearBuffer();

    // Decorative double frame
    _display.drawFrame(0, 0, 128, 64);
    _display.drawFrame(2, 2, 124, 60);

    // Title (7x14B: 6 chars * 7px = 42px, centered)
    _display.setFont(u8g2_font_7x14B_tr);
    _display.drawStr(43, 6, "SAWARI");

    // Subtitle (6x10: 18 chars * 6px = 108px,  centered)
    _display.setFont(u8g2_font_6x10_tr);
    _display.drawStr(10, 22, "Bus Telemetry v1.0");

    // Simple bus icon, centered
    int bx = 54, by = 36;
    _display.drawFrame(bx, by, 20, 10);       // Body
    _display.drawBox(bx + 2, by + 2, 5, 5);   // Window 1
    _display.drawBox(bx + 9, by + 2, 5, 5);   // Window 2
    _display.drawDisc(bx + 4, by + 10, 2);    // Wheel 1
    _display.drawDisc(bx + 15, by + 10, 2);   // Wheel 2

    // Status text (bottom=49+10=59, inside inner frame y=61)
    _display.drawStr(34, 49, "Booting...");
    _display.sendBuffer();

    Serial.println(F("[DISPLAY] OLED initialized — boot splash shown"));
}

// ============================================================================
// 2. BOOT PROGRESS — displayBootProgress()
// ============================================================================
//
//  128x64 layout:
//  y=2:  "SAWARI" (7x14B, 42px, x=43)            bottom=16
//  y=18: Bus icon body 20x10, wheels at y=28      bottom=30
//  y=34: Progress bar x=14, w=84, h=10            right=98, bottom=44
//        Percentage text at x=100                  "100%"→x=124
//  y=50: Status text at x=4                        bottom=60
//
void displayBootProgress(int progress, const char* status) {
    _display.clearBuffer();

    _display.setFont(u8g2_font_7x14B_tr);
    _display.drawStr(43, 2, "SAWARI");

    // Bus icon
    _display.setFont(u8g2_font_6x10_tr);
    int bx = 54, by = 18;
    _display.drawFrame(bx, by, 20, 10);
    _display.drawBox(bx + 2, by + 2, 5, 5);
    _display.drawBox(bx + 9, by + 2, 5, 5);
    _display.drawDisc(bx + 4, by + 10, 2);
    _display.drawDisc(bx + 15, by + 10, 2);

    // Progress bar (w=84 so bar ends at x=98, percentage at x=100)
    int barX = 14, barY = 34, barW = 84, barH = 10;
    _display.drawFrame(barX, barY, barW, barH);
    int fillW = (progress * (barW - 2)) / 100;
    if (fillW > 0) {
        _display.drawBox(barX + 1, barY + 1, fillW, barH - 2);
    }

    // Percentage (max "100%" = 4*6=24px at x=100 → ends at x=124 ✓)
    snprintf(_lineBuf, sizeof(_lineBuf), "%d%%", progress);
    _display.drawStr(barX + barW + 2, barY + 1, _lineBuf);

    // Status text (max ~19 chars * 6 = 114px at x=4 → 118 ✓)
    _display.drawStr(4, 50, status);

    _display.sendBuffer();
}

// ============================================================================
// 3. WIFI SETUP — displayWiFiSetup()
// ============================================================================
//
//  128x64 layout inside frame:
//  y=0-4:  WiFi semicircle waves (top-clipped circles)
//  y=28:   "WiFi Setup" (6x10, 60px, x=34)        bottom=38
//  y=40:   "Connect to AP:" (6x10, 84px, x=10)    bottom=50
//  y=50:   AP_NAME (7x14B, 84px, x=22)            bottom=64→use y=48→62
//
void displayWiFiSetup() {
    _display.clearBuffer();
    _display.drawFrame(0, 0, 128, 64);

    // WiFi icon (semi-circle waves)
    int cx = 64, cy = 12;
    _display.drawCircle(cx, cy + 8, 4);
    _display.drawCircle(cx, cy, 10);
    _display.drawCircle(cx, cy, 16);
    _display.setDrawColor(0);
    _display.drawBox(cx - 20, cy + 2, 40, 20);
    _display.setDrawColor(1);
    _display.drawDisc(cx, cy + 8, 3);

    _display.setFont(u8g2_font_6x10_tr);
    _display.drawStr(34, 28, "WiFi Setup");
    _display.drawStr(10, 40, "Connect to AP:");

    // AP name (7x14B: 12*7=84px at x=22 → 106, bottom=48+14=62 inside frame)
    _display.setFont(u8g2_font_7x14B_tr);
    _display.drawStr(22, 48, AP_NAME);

    _display.sendBuffer();
}

// ============================================================================
// 3b. WIFI PORTAL ACTIVE — displayPortalActive()
// ============================================================================
//
//  128x64 layout inside frame:
//  y=3:  "WiFi Portal" (7x14B, 77px, x=4)       bottom=17
//        Spinner at (115,10) r=7                  x=108-122, y=3-17
//  y=19: ">> ACTIVE <<" blink (6x10, 72px, x=28) bottom=29
//  y=31: "AP:" + apName (6x10, max 90px, x=4)    bottom=41
//  y=42: "IP:" + portalIP (6x10, max 84px, x=4)  bottom=52
//  y=53: "Join AP from phone" (6x10, 108px, x=4) bottom=63 = frame bottom
//
void displayPortalActive(const char* apName, const char* portalIP) {
    _display.clearBuffer();
    _display.setFont(u8g2_font_6x10_tr);
    _display.drawFrame(0, 0, 128, 64);

    // Header (7x14B)
    _display.setFont(u8g2_font_7x14B_tr);
    _display.drawStr(4, 3, "WiFi Portal");
    _display.setFont(u8g2_font_6x10_tr);

    // Spinner (r=7: x=108–122, y=3–17, inside frame)
    _drawSpinner(115, 10, 7);

    // Blinking ACTIVE status
    if (_blinkState) {
        _display.drawStr(28, 19, ">> ACTIVE <<");
    }

    // AP name (max "AP:SAWARI_SETUP" = 15*6=90px → x=4+90=94 ✓)
    snprintf(_lineBuf, sizeof(_lineBuf), "AP:%s", apName);
    _display.drawStr(4, 31, _lineBuf);

    // Portal IP (max "IP:192.168.4.1" = 14*6=84px → x=4+84=88 ✓)
    snprintf(_lineBuf, sizeof(_lineBuf), "IP:%s", portalIP);
    _display.drawStr(4, 42, _lineBuf);

    // Instruction (18*6=108px at x=10 → 118, bottom=53+10=63 = frame edge ✓)
    _display.drawStr(10, 53, "Join AP from phone");

    _display.sendBuffer();
}

// ============================================================================
// 3c. CONNECTING WIFI — displayConnectingWiFi()
// ============================================================================
//
//  128x64 layout (no frame):
//  y=18: "Connecting to" (6x10, 78px, x=25)       bottom=28
//  y=30: "WiFi..." (6x10, 42px, x=43)             bottom=40
//        Spinner at (64,52) r=10                    y=42-62
//
void displayConnectingWiFi() {
    _display.clearBuffer();
    _display.setFont(u8g2_font_6x10_tr);

    _display.drawStr(25, 18, "Connecting to");
    _display.drawStr(43, 30, "WiFi...");

    _drawSpinner(64, 52, 10);

    _display.sendBuffer();
}

// ============================================================================
// 3d. WIFI CONNECTED — displayWiFiConnected()
// ============================================================================
//
//  128x64 layout inside frame:
//  y=6:  "Connected!" (7x14B, 70px, x=29)        bottom=20
//  y=24: "SSID:" + ssid (6x10, x=4/34)           bottom=34
//  y=36: "IP:" + ip (6x10, x=4/24)               bottom=46
//  y=50: WiFi bars + RSSI dBm (6x10)             bottom=60
//
void displayWiFiConnected(const char* ssid, const char* ip, int rssi) {
    _display.clearBuffer();
    _display.drawFrame(0, 0, 128, 64);
    _display.setFont(u8g2_font_6x10_tr);

    // "Connected!" bold centered (7x14B: 10*7=70px at x=29, bottom=20)
    _display.setFont(u8g2_font_7x14B_tr);
    _display.drawStr(29, 6, "Connected!");
    _display.setFont(u8g2_font_6x10_tr);

    // SSID (label at x=4, value at x=34; max 15ch=90px → 34+90=124 ✓)
    _display.drawStr(4, 24, "SSID:");
    _display.drawStr(34, 24, ssid);

    // IP address (label at x=4, value at x=24; max 15ch=90px → 24+90=114 ✓)
    _display.drawStr(4, 36, "IP:");
    _display.drawStr(24, 36, ip);

    // Signal: bars(16px wide) + dBm text (bottom=50+10=60, inside frame ✓)
    _display.drawStr(4, 50, "Signal:");
    _drawWiFiBars(48, 50, rssi, true);
    snprintf(_lineBuf, sizeof(_lineBuf), "%ddBm", rssi);
    _display.drawStr(68, 50, _lineBuf);

    _display.sendBuffer();
}

// ============================================================================
// 4. GPS SEARCHING — displaySearchingGPS()
// ============================================================================
//
//  128x64 layout:
//  y=0:  "BUS:X" x=0 | WiFi x=34 | "Q:X" x=108   bottom=10
//
//  LEFT (x=0–50):                RIGHT (x=56–127):
//  Radar center(26,36) r=15       y=14: "Searching"    (x=56)
//    circle: x=11–41, y=21–51     y=26: "GPS..."       (x=56)
//                                 y=38: "Sats: X/4"    (x=56, 60px→116)
//                                 y=50: progress bar   (x=56, w=60, h=6→56)
//
void displaySearchingGPS(int satellites, bool wifiOk, const char* wifiSSID, int queueCount) {
    _display.clearBuffer();
    _display.setFont(u8g2_font_6x10_tr);

    // Header row: BUS ID + WiFi info + queue count
    snprintf(_lineBuf, sizeof(_lineBuf), "BUS:%d", BUS_ID);
    _display.drawStr(0, 0, _lineBuf);

    // WiFi SSID or OFFLINE (max 8 chars = 48px at x=34 → 82 ✓)
    if (wifiOk) {
        char shortSSID[9];
        strncpy(shortSSID, wifiSSID, 8);
        shortSSID[8] = '\0';
        _display.drawStr(34, 0, shortSSID);
    } else {
        _display.drawStr(34, 0, "[OFFLINE]");
    }

    // Queue count at far right (max "Q:500" = 5*6=30px at x=98 → 128 ✓)
    if (queueCount > 0) {
        snprintf(_lineBuf, sizeof(_lineBuf), "Q:%d", queueCount);
        _display.drawStr(98, 0, _lineBuf);
    }

    // Animated radar (center 26,36 r=15: x=11–41, y=21–51)
    _drawGPSRadar(26, 36, 15, satellites);

    // Status text on the right side
    _display.drawStr(56, 14, "Searching");
    _display.drawStr(56, 26, "GPS...");

    snprintf(_lineBuf, sizeof(_lineBuf), "Sats: %d/4", satellites);
    _display.drawStr(56, 38, _lineBuf);

    // Satellite acquisition progress bar (x=56, w=60 → 116, bottom=50+6=56 ✓)
    int progress = min(satellites, 4);
    _display.drawFrame(56, 50, 60, 6);
    if (progress > 0) {
        int fillW = progress * ((60 - 2) / 4);   // 14px per sat
        _display.drawBox(57, 51, fillW, 4);
    }

    _display.sendBuffer();
}

// ============================================================================
// 5. MAIN TELEMETRY STATUS — displayShowStatus()
// ============================================================================
//
//  128x64 layout, 6 rows of 6x10 font with 1px gaps:
//
//  y=0:  [WiFi bars 16px] "BUS:X" x=18  SSID/OFFLINE x=80        bot=10
//  y=11: "LAT:XX.XXXX"  x=0  (max13ch=78px)   [compass r=5 at 120,16] bot=21
//  y=22: "LON:XX.XXXX"  x=0  (max13ch=78px)                       bot=32
//  y=33: "SPD:XX" x=0   [bar x=38,w=50] "km/h" x=90              bot=43
//  y=44: "SAT:XX" x=0   "HDOP:XX.X" x=42                         bot=54
//  y=54: ">> LIVE" / "OFFLINE Q:X" / "SYNC Q:X"                   bot=64→63
//
void displayShowStatus(const TelemetryData* data, bool wifiOk, int wifiRSSI,
                       const char* wifiSSID, int queueCount, bool isOffline) {
    _display.clearBuffer();
    _display.setFont(u8g2_font_6x10_tr);

    // === Row 0 (y=0): WiFi bars + BUS ID + SSID ===
    _drawWiFiBars(0, 0, wifiRSSI, wifiOk);

    snprintf(_lineBuf, sizeof(_lineBuf), "BUS:%d", BUS_ID);
    _display.drawStr(18, 0, _lineBuf);

    if (wifiOk) {
        // Truncate SSID to 8 chars (8*6=48px at x=80 → 128 ✓)
        char shortSSID[9];
        strncpy(shortSSID, wifiSSID, 8);
        shortSSID[8] = '\0';
        _display.drawStr(80, 0, shortSSID);
    } else {
        if (_blinkState) _display.drawStr(80, 0, "OFFLINE");
    }

    // === Row 1 (y=11): Latitude + small compass ===
    snprintf(_lineBuf, sizeof(_lineBuf), "LAT:%.4f", data->latitude);
    _display.drawStr(0, 11, _lineBuf);

    // Small compass (r=5, center 120,16: circle x=115–125, y=11–21)
    _drawCompassSmall(120, 16, 5, data->direction);

    // === Row 2 (y=22): Longitude ===
    snprintf(_lineBuf, sizeof(_lineBuf), "LON:%.4f", data->longitude);
    _display.drawStr(0, 22, _lineBuf);

    // === Row 3 (y=33): Speed + bar + km/h ===
    snprintf(_lineBuf, sizeof(_lineBuf), "SPD:%2d", (int)data->speed);
    _display.drawStr(0, 33, _lineBuf);
    _drawSpeedBar(38, 35, 50, (int)data->speed, 80);
    _display.drawStr(90, 33, "km/h");

    // === Row 4 (y=44): Satellites + HDOP ===
    snprintf(_lineBuf, sizeof(_lineBuf), "SAT:%d", data->satellites);
    _display.drawStr(0, 44, _lineBuf);

    char hdopStr[6];
    dtostrf(data->hdop, 3, 1, hdopStr);
    snprintf(_lineBuf, sizeof(_lineBuf), "HDOP:%s", hdopStr);
    _display.drawStr(42, 44, _lineBuf);

    // === Row 5 (y=54): Status line (bottom=54+10=64 → last pixel row 63) ===
    if (isOffline && queueCount > 0) {
        snprintf(_lineBuf, sizeof(_lineBuf), "OFFLINE Q:%d", queueCount);
        if (_blinkState) _display.drawStr(0, 54, _lineBuf);
    } else if (queueCount > 0) {
        snprintf(_lineBuf, sizeof(_lineBuf), "SYNC Q:%d", queueCount);
        _display.drawStr(0, 54, _lineBuf);
    } else if (wifiOk) {
        _display.drawStr(0, 54, ">> LIVE");
    } else {
        if (_blinkState) _display.drawStr(0, 54, ">> OFFLINE");
    }

    _display.sendBuffer();
}

// ============================================================================
// 6. OFFLINE MODE INFO — displayOfflineMode()
// ============================================================================
//
//  128x64 layout inside frame:
//  y=3:  "OFFLINE MODE" (7x14B, 84px, x=10)       bottom=17
//  y=19: "WiFi unavailable" (6x10, x=4)            bottom=29
//  y=30: "Data stored locally" (6x10, x=4)         bottom=40
//  y=41: "Queued: X records" (6x10, x=4)           bottom=51
//  y=52: "Retry in: Xs" (6x10, x=4)                bottom=62 ✓
//        Storage icon at (110,22) blink
//
void displayOfflineMode(int queueCount, int secUntilRetry) {
    _display.clearBuffer();
    _display.drawFrame(0, 0, 128, 64);
    _display.setFont(u8g2_font_6x10_tr);

    // Title (7x14B: 12*7=84px at x=10 → 94, bottom=17)
    _display.setFont(u8g2_font_7x14B_tr);
    _display.drawStr(10, 3, "OFFLINE MODE");
    _display.setFont(u8g2_font_6x10_tr);

    _display.drawStr(4, 19, "WiFi unavailable");
    _display.drawStr(4, 30, "Data stored locally");

    snprintf(_lineBuf, sizeof(_lineBuf), "Queued: %d records", queueCount);
    _display.drawStr(4, 41, _lineBuf);

    snprintf(_lineBuf, sizeof(_lineBuf), "Retry in: %ds", secUntilRetry);
    _display.drawStr(4, 52, _lineBuf);

    // Blinking storage icon (small disk)
    if (_blinkState) {
        _display.drawFrame(110, 22, 12, 10);
        _display.drawBox(112, 24, 8, 2);
    }

    _display.sendBuffer();
}

// ============================================================================
// ANIMATION TICK — call from loop()
// ============================================================================
void displayAnimationTick() {
    unsigned long now = millis();
    if (now - _lastAnimationTick >= ANIMATION_INTERVAL) {
        _lastAnimationTick = now;
        _radarAngle = (_radarAngle + 10) % 360;
        _blinkState = !_blinkState;
        _spinnerFrame = (_spinnerFrame + 1) % 8;
    }
}
