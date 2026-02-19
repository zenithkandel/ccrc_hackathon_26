# SAWARI Bus Telemetry Device

## Hardware Assembly Guide

Complete hardware documentation for building an ESP32-based GPS telemetry unit for bus fleet tracking.

---

## Table of Contents

1. [Overview](#overview)
2. [Bill of Materials](#bill-of-materials)
3. [Wiring Diagram](#wiring-diagram)
4. [Pin Connections](#pin-connections)
5. [Power Supply Design](#power-supply-design)
6. [Enclosure Requirements](#enclosure-requirements)
7. [Assembly Instructions](#assembly-instructions)
8. [LED Indicators](#led-indicators)
9. [Testing Procedure](#testing-procedure)
10. [Troubleshooting](#troubleshooting)
11. [Safety Considerations](#safety-considerations)

---

## Overview

The SAWARI Telemetry Device is a ruggedized GPS tracker designed for automotive deployment in buses. It provides:

- Real-time GPS location tracking
- WiFi connectivity for data transmission
- Offline data buffering during connectivity loss
- Visual status display (OLED)
- LED status indicators

**Target Environment:** 12V automotive electrical system, vibration, temperature variations

---

## Bill of Materials

### Core Components

| Qty | Component | Specifications | Approx. Cost |
|-----|-----------|----------------|--------------|
| 1 | ESP32 Dev Module | 38-pin, 4MB Flash, WiFi+BT | $5-8 |
| 1 | NEO-6M GPS Module | UART, with ceramic antenna | $8-12 |
| 1 | 1.3" OLED Display | SH1106 or SSD1306, I2C, 128x64 | $4-6 |
| 1 | Buck Converter | LM2596 or MP1584, 12V→5V, 3A | $2-4 |
| 4 | LEDs (3mm or 5mm) | Green, Blue, Yellow, Red | $1 |
| 4 | Resistors | 220Ω or 330Ω, 1/4W | $0.50 |
| 1 | Capacitor | 100µF 25V Electrolytic | $0.50 |
| 1 | Capacitor | 10µF 16V Ceramic | $0.30 |
| 1 | TVS Diode | P6KE18A or equivalent | $0.50 |
| 1 | Fuse Holder + Fuse | Blade type, 2A | $1 |
| 1 | Enclosure | IP65 rated, ~100x68x50mm | $5-10 |

### Connectors & Wiring

| Qty | Component | Purpose |
|-----|-----------|---------|
| 1 | JST-XH 2-pin connector | Power input from vehicle |
| 1 | 4-pin header | GPS module connection |
| 1 | 4-pin header | OLED display connection |
| - | 22 AWG wire (various colors) | Internal wiring |
| - | Heat shrink tubing | Wire insulation |
| 1 | SMA extension cable (optional) | External GPS antenna |

### Tools Required

- Soldering iron (temperature controlled, 350°C)
- Solder (60/40 or lead-free)
- Wire strippers
- Multimeter
- Hot glue gun
- Screwdrivers (Phillips, small flat)

---

## Wiring Diagram

```
                                    ┌─────────────────────────────────┐
                                    │         ESP32 Dev Module        │
                                    │                                 │
    ┌──────────────┐                │    3V3 ●           ● VIN (5V)   │
    │  12V Vehicle │                │    GND ●           ● GND        │
    │    Power     │                │   GPIO16 ●         ● GPIO2      │──── LED (Power)
    └──────┬───────┘                │   GPIO17 ●         ● GPIO4      │──── LED (WiFi)
           │                        │   GPIO21 ●         ● GPIO13     │──── LED (GPS)
           │ +12V                   │   GPIO22 ●         ● GPIO14     │──── LED (Data)
    ┌──────┴───────┐                │                                 │
    │   2A Fuse    │                └────────────────┬────────────────┘
    └──────┬───────┘                                 │
           │                                         │
    ┌──────┴───────┐                    ┌────────────┴────────────┐
    │  TVS Diode   │                    │                         │
    │  (P6KE18A)   │                    │    Buck Converter       │
    └──────┬───────┘                    │    (LM2596 / MP1584)    │
           │                            │                         │
           │                            │   IN+ ────────┬──── OUT+ ──→ ESP32 VIN
           │ ──────────────────────────────────────────>│
           │                            │   IN- ────────┴──── OUT- ──→ ESP32 GND
    Vehicle GND ───────────────────────────────────────>│
                                        │                         │
                                        │   [Adjust to 5V output] │
                                        └─────────────────────────┘


    ┌─────────────────┐                  ┌─────────────────┐
    │   NEO-6M GPS    │                  │   1.3" OLED     │
    │                 │                  │   (SH1106)      │
    │   VCC ──────────│── 3.3V           │                 │
    │   GND ──────────│── GND            │   VCC ──────────│── 3.3V
    │   TX  ──────────│── GPIO16 (RX2)   │   GND ──────────│── GND
    │   RX  ──────────│── GPIO17 (TX2)   │   SDA ──────────│── GPIO21
    │                 │                  │   SCL ──────────│── GPIO22
    └─────────────────┘                  └─────────────────┘


    LED Connections (Active HIGH):
    
    GPIO2  ──┬── 220Ω ──┬── LED (Power/Green) ──┬── GND
    GPIO4  ──┤          ├── LED (WiFi/Blue)   ──┤
    GPIO13 ──┤          ├── LED (GPS/Yellow)  ──┤
    GPIO14 ──┘          └── LED (Data/Red)    ──┘
```

---

## Pin Connections

### ESP32 Pin Assignment Table

| GPIO | Function | Connected To | Wire Color (Suggested) |
|------|----------|--------------|------------------------|
| VIN | 5V Power Input | Buck Converter OUT+ | Red |
| GND | Ground | Common Ground | Black |
| 3V3 | 3.3V Output | GPS VCC, OLED VCC | Orange |
| GPIO16 | UART2 RX | GPS TX | Yellow |
| GPIO17 | UART2 TX | GPS RX | Green |
| GPIO21 | I2C SDA | OLED SDA | Blue |
| GPIO22 | I2C SCL | OLED SCL | Purple |
| GPIO2 | LED Output | Power LED (Green) | White |
| GPIO4 | LED Output | WiFi LED (Blue) | White |
| GPIO13 | LED Output | GPS LED (Yellow) | White |
| GPIO14 | LED Output | Data LED (Red) | White |

### GPS Module (NEO-6M) Pinout

| Pin | Function | Connect To |
|-----|----------|------------|
| VCC | Power (2.7-3.6V) | ESP32 3V3 |
| GND | Ground | ESP32 GND |
| TX | UART Transmit (data out) | ESP32 GPIO16 |
| RX | UART Receive (data in) | ESP32 GPIO17 |
| PPS | Pulse Per Second (optional) | Not connected |

### OLED Display (SH1106/SSD1306) Pinout

| Pin | Function | Connect To |
|-----|----------|------------|
| VCC | Power (3.3V-5V) | ESP32 3V3 |
| GND | Ground | ESP32 GND |
| SDA | I2C Data | ESP32 GPIO21 |
| SCL | I2C Clock | ESP32 GPIO22 |

---

## Power Supply Design

### Automotive Power Challenges

Vehicle electrical systems present several challenges:

| Challenge | Solution |
|-----------|----------|
| Voltage spikes (load dump up to 40V) | TVS diode clamping |
| Voltage sag (cranking, 6-8V) | Buck converter handles wide input |
| Reverse polarity | Fuse protection (blows safely) |
| Electrical noise | Input/output capacitors |
| Over-current | 2A fuse protection |

### Buck Converter Setup (LM2596)

1. **Input voltage range:** 7-35V (handles 12V nominal with headroom)
2. **Output voltage:** Adjust potentiometer to exactly **5.0V** (±0.1V)
3. **Output current:** 3A max (ESP32 + peripherals use ~500mA peak)

**Adjustment Procedure:**
1. Connect 12V input to buck converter
2. Turn potentiometer clockwise until output reads 5.0V
3. Verify with multimeter before connecting ESP32
4. Add load (100Ω resistor) and re-verify voltage holds

### Protection Circuit

```
Vehicle +12V ──── [2A Fuse] ──── [TVS P6KE18A] ──┬── [100µF Cap] ──── Buck IN+
                                                │
Vehicle GND ────────────────────────────────────┴───────────────── Buck IN-
```

### Power Consumption Estimates

| Component | Typical Current | Peak Current |
|-----------|-----------------|--------------|
| ESP32 (WiFi active) | 80mA | 240mA |
| NEO-6M GPS | 35mA | 50mA |
| OLED Display | 20mA | 30mA |
| LEDs (all on) | 40mA | 40mA |
| **Total** | **175mA** | **360mA** |

---

## Enclosure Requirements

### Environmental Rating

- **IP Rating:** IP65 minimum (dust-tight, water jet protected)
- **Temperature Range:** -20°C to +70°C operating
- **Vibration:** Mount with rubber grommets or foam padding

### Recommended Dimensions

- **Internal space:** Minimum 90 x 60 x 40mm
- **External:** ~100 x 68 x 50mm with wall thickness

### Mounting Considerations

```
┌─────────────────────────────────────────────┐
│                   ENCLOSURE                  │
│  ┌───────────────────────────────────────┐  │
│  │  [OLED Display Window - Clear Acrylic] │  │──── View window cutout
│  └───────────────────────────────────────┘  │
│                                             │
│    (●) (●) (●) (●)  ← LED light pipes      │
│    PWR WiFi GPS DATA                        │
│                                             │
│  ┌─────────────────┐                        │
│  │     ESP32       │   ┌────────┐           │
│  │                 │   │  GPS   │           │
│  │                 │   │ Module │           │
│  └─────────────────┘   └────────┘           │
│                                             │
│  [────Buck Converter────]                   │
│                                             │
│  Cable gland ◎                              │──── IP65 cable entry for power
└─────────────────────────────────────────────┘
```

### GPS Antenna Placement

- **Internal ceramic antenna:** Position GPS module near enclosure top, away from metal
- **External antenna (optional):** Use SMA extension to rooftop antenna for better signal

---

## Assembly Instructions

### Step 1: Prepare Components

1. Test ESP32 with USB (upload test sketch)
2. Test OLED display (run I2C scanner)
3. Test GPS module (connect to serial and verify NMEA output)
4. Adjust buck converter to 5.0V output

### Step 2: Build Power Supply

1. Solder 100µF capacitor across buck converter input
2. Solder 10µF capacitor across buck converter output
3. Connect TVS diode (cathode bar toward positive)
4. Install fuse holder in positive line
5. Add JST connector for vehicle power

### Step 3: Wire ESP32 to Peripherals

**GPS Module:**
```
ESP32 3V3  ──────── GPS VCC
ESP32 GND  ──────── GPS GND
ESP32 GPIO16 ────── GPS TX
ESP32 GPIO17 ────── GPS RX
```

**OLED Display:**
```
ESP32 3V3  ──────── OLED VCC
ESP32 GND  ──────── OLED GND
ESP32 GPIO21 ────── OLED SDA
ESP32 GPIO22 ────── OLED SCL
```

### Step 4: Wire LEDs

For each LED:
```
ESP32 GPIOxx ──── 220Ω Resistor ──── LED Anode (+) ──── LED Cathode (-) ──── GND
```

LED assignments:
- GPIO2 → Green (Power)
- GPIO4 → Blue (WiFi)
- GPIO13 → Yellow (GPS Lock)
- GPIO14 → Red (Data Send)

### Step 5: Connect Power

1. Connect buck converter output to ESP32:
   - OUT+ → ESP32 VIN
   - OUT- → ESP32 GND
2. Connect all component grounds together (star ground configuration)

### Step 6: Enclosure Assembly

1. Mark and drill holes for:
   - OLED display window
   - LED holes (3mm or 5mm)
   - Cable gland
   - Mounting holes
2. Install clear acrylic window for OLED
3. Install LED light pipes or diffusers
4. Mount components with standoffs or hot glue
5. Route wires neatly, secure with zip ties
6. Install cable gland and seal

---

## LED Indicators

### LED Behavior Reference

| LED | Color | State | Meaning |
|-----|-------|-------|---------|
| Power | Green | Solid ON | Device powered and running |
| WiFi | Blue | Solid ON | Connected to WiFi network |
| WiFi | Blue | OFF | WiFi disconnected / AP mode |
| GPS | Yellow | Solid ON | GPS has valid position fix |
| GPS | Yellow | OFF | Searching for GPS satellites |
| Data | Red | Blink (150ms) | Successfully sent data to server |
| Data | Red | OFF | Idle / Not transmitting |

### Visual Status Quick Reference

| Power | WiFi | GPS | Data | System Status |
|-------|------|-----|------|---------------|
| ● | ● | ● | ◐ | Normal operation (online, GPS lock) |
| ● | ○ | ● | ○ | Offline mode (queuing data locally) |
| ● | ● | ○ | ○ | GPS searching (WiFi ready) |
| ● | ○ | ○ | ○ | Startup / Connecting |

Legend: ● = ON, ○ = OFF, ◐ = Blinking

---

## Testing Procedure

### Pre-Power Checks

1. **Visual inspection:** Check for solder bridges, loose wires
2. **Continuity test:** Verify no shorts between VIN and GND
3. **Resistance check:** Each LED circuit should show ~300Ω to GND

### Power-On Sequence Test

1. Connect 12V power supply (bench supply recommended first)
2. Verify buck converter output: 5.0V ±0.1V
3. Observe boot sequence:
   - Power LED turns ON immediately
   - OLED shows splash screen
   - Progress bar advances
   - WiFi LED behavior based on connection

### GPS Acquisition Test

1. Place device near window or outdoors
2. Cold start may take 30-90 seconds for first fix
3. GPS LED turns ON when fix acquired
4. OLED shows coordinates and satellite count

### WiFi Configuration Test

1. On first boot, device creates "SAWARI_SETUP" access point
2. Connect phone/laptop to this network
3. Open browser → captive portal appears
4. Select WiFi network and enter password
5. Device saves credentials and connects

### Data Transmission Test

1. Configure valid API endpoint in firmware
2. Ensure WiFi connected (WiFi LED ON)
3. Ensure GPS fix (GPS LED ON)
4. Data LED should blink every 2 seconds
5. Verify data received on server

---

## Troubleshooting

### ESP32 Not Powering On

| Symptom | Possible Cause | Solution |
|---------|----------------|----------|
| No LEDs, no display | Fuse blown | Check/replace fuse |
| | Buck converter dead | Measure input/output voltage |
| | VIN/GND reversed | Check polarity |
| Resets repeatedly | Insufficient current | Use beefier power supply |
| | Brown-out | Check for voltage dips |

### GPS Not Getting Fix

| Symptom | Possible Cause | Solution |
|---------|----------------|----------|
| 0 satellites | Antenna blocked | Move near window/outdoors |
| | Bad antenna connection | Check antenna solder/connector |
| | GPS module dead | Test with USB serial adapter |
| Low sat count (1-3) | Poor antenna placement | Reposition away from metal |
| Fix takes very long | Cold start from scratch | Wait up to 15 minutes |

### OLED Display Issues

| Symptom | Possible Cause | Solution |
|---------|----------------|----------|
| Blank screen | Wrong I2C address | Try 0x3C or 0x3D |
| | SDA/SCL swapped | Check wiring |
| | Display dead | Test with separate sketch |
| Garbled display | Wrong controller | Change SH1106 ↔ SSD1306 |
| Dim display | Low voltage on VCC | Verify 3.3V supply |

### WiFi Connection Problems

| Symptom | Possible Cause | Solution |
|---------|----------------|----------|
| Won't connect | Wrong password saved | Erase flash, reconfigure |
| | Router too far | Move closer or add external antenna |
| Keeps disconnecting | Interference | Change router channel |
| AP mode won't start | Corrupted config | Factory reset ESP32 |

### Data Not Sending

| Symptom | Possible Cause | Solution |
|---------|----------------|----------|
| No blink, WiFi OK | GPS no fix | Check GPS LED |
| | API endpoint wrong | Verify URL in config.h |
| | Server down | Test endpoint manually |
| Blinks but server empty | HTTP error | Check server logs |
| | JSON format issue | Serial debug payload |

---

## Safety Considerations

### Electrical Safety

⚠️ **WARNING: Vehicle electrical systems can be dangerous**

- Always disconnect vehicle battery before installation
- Use proper gauge wire (18-22 AWG for signal, 16 AWG for power)
- Fuse protection is mandatory
- Keep connections away from heat sources and moving parts
- Do not install while vehicle is running

### Installation Location

✅ **DO:**
- Mount in protected, ventilated area
- Keep GPS antenna view to sky (dashboard or roof)
- Secure all wiring with clips/ties
- Use waterproof enclosure for external mounting

❌ **DON'T:**
- Block driver's view
- Install near airbags
- Route wires near pedals or steering
- Mount on engine or hot surfaces

### Compliance Notes

- Device may require type approval for commercial vehicle use
- Check local regulations for GPS tracking disclosure requirements
- EMC compliance may be needed for commercial deployment

---

## Firmware Upload

### Arduino IDE Setup

1. Install ESP32 board support:
   - File → Preferences → Additional Board URLs:
   ```
   https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json
   ```
   - Tools → Board → Boards Manager → Search "ESP32" → Install

2. Install required libraries (Library Manager):
   - **TinyGPSPlus** by Mikal Hart
   - **U8g2** by Oliver Kraus
   - **WiFiManager** by tzapu

3. Board settings:
   - Board: ESP32 Dev Module
   - Upload Speed: 921600
   - Flash Frequency: 80MHz
   - Partition Scheme: Default 4MB with spiffs

4. Open `sawari_telemetry.ino` and upload

### Configuration

Edit `config.h` before uploading:

```cpp
#define BUS_ID              1                                    // Your bus ID
#define API_ENDPOINT        "https://your-server.com/api/trips/log.php"  // Your API
```

---

## Technical Specifications

| Parameter | Value |
|-----------|-------|
| Input Voltage | 9-16V DC (12V nominal) |
| Power Consumption | 175mA typical, 360mA peak |
| GPS Accuracy | 2.5m CEP (open sky) |
| WiFi Range | ~50m indoor, ~100m outdoor |
| Data Rate | 1 update every 2 seconds |
| Offline Storage | 500 records (~100KB) |
| Operating Temperature | -20°C to +70°C |
| Dimensions (enclosure) | 100 x 68 x 50mm |
| Weight | ~120g assembled |

---

## Support

For issues and contributions, please refer to the project repository.

**SAWARI Transport Intelligence Platform**  
Bus Fleet Telemetry Device v1.0

---

*Last updated: February 2026*
