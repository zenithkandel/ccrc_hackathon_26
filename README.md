<p align="center">
  <img src="https://img.shields.io/badge/Platform-Web%20%2B%20IoT-blue?style=for-the-badge" alt="Platform">
  <img src="https://img.shields.io/badge/Language-PHP%20%7C%20JavaScript%20%7C%20C++-green?style=for-the-badge" alt="Languages">
  <img src="https://img.shields.io/badge/Hardware-ESP32-red?style=for-the-badge" alt="Hardware">
  <img src="https://img.shields.io/badge/Status-Complete-brightgreen?style=for-the-badge" alt="Status">
  <img src="https://img.shields.io/badge/License-MIT-yellow?style=for-the-badge" alt="License">
</p>

# 🚍 SAWARI — Navigate Nepal's Public Transport

**Sawari** (सवारी — meaning "vehicle/ride" in Nepali) is a full-stack public transportation navigation and live bus tracking platform built for the chaotic, informal transit systems of Nepal. It combines a **web application** with **custom IoT GPS hardware** to tell users exactly which bus to take, where to board, what to say to the conductor, how much to pay, and tracks the bus live on a map.

> **Built for CCRC Nexus IT Fest Hackathon 2026**

---

## Table of Contents

- [1. Project Overview](#1-project-overview)
  - [1.1 Problem Statement](#11-problem-statement)
  - [1.2 Solution](#12-solution)
  - [1.3 Key Objectives](#13-key-objectives)
  - [1.4 Real-World Use Case](#14-real-world-use-case)
- [2. System Architecture](#2-system-architecture)
  - [2.1 High-Level Architecture](#21-high-level-architecture)
  - [2.2 Data Flow](#22-data-flow)
  - [2.3 Hardware–Software Interaction](#23-hardwaresoftware-interaction)
- [3. Hardware Section](#3-hardware-section)
  - [3.1 Component Overview](#31-component-overview)
  - [3.2 Microcontroller — ESP32 Dev Module](#32-microcontroller--esp32-dev-module)
  - [3.3 GPS Module — NEO-6M](#33-gps-module--neo-6m)
  - [3.4 OLED Display — 1.3" SH1106](#34-oled-display--13-sh1106)
  - [3.5 Power System](#35-power-system)
  - [3.6 LED Status Indicators](#36-led-status-indicators)
  - [3.7 Complete Pin Mapping Table](#37-complete-pin-mapping-table)
  - [3.8 Wiring Diagram](#38-wiring-diagram)
  - [3.9 Protection Circuit](#39-protection-circuit)
  - [3.10 Component Alternatives](#310-component-alternatives)
- [4. Software Section](#4-software-section)
  - [4.1 Tech Stack](#41-tech-stack)
  - [4.2 Folder Structure](#42-folder-structure)
  - [4.3 Code Architecture](#43-code-architecture)
  - [4.4 Module-Wise Explanation](#44-module-wise-explanation)
  - [4.5 Database Schema](#45-database-schema)
  - [4.6 API Endpoints](#46-api-endpoints)
  - [4.7 Routing Engine](#47-routing-engine)
  - [4.8 Fare Calculation](#48-fare-calculation)
  - [4.9 Error Handling Strategy](#49-error-handling-strategy)
  - [4.10 Security Implementation](#410-security-implementation)
- [5. Firmware Features](#5-firmware-features)
  - [5.1 Feature List](#51-feature-list)
  - [5.2 Boot Sequence & UI Flow](#52-boot-sequence--ui-flow)
  - [5.3 Display UI Design](#53-display-ui-design)
  - [5.4 Data Logging System](#54-data-logging-system)
  - [5.5 WiFi Handling Logic](#55-wifi-handling-logic)
  - [5.6 Offline Data Storage Logic](#56-offline-data-storage-logic)
  - [5.7 Error Detection System](#57-error-detection-system)
- [6. Web Interface](#6-web-interface)
  - [6.1 Public User Map](#61-public-user-map)
  - [6.2 Agent Dashboard](#62-agent-dashboard)
  - [6.3 Admin Dashboard](#63-admin-dashboard)
  - [6.4 Application Pages](#64-application-pages)
- [7. Installation & Setup Guide](#7-installation--setup-guide)
  - [7.1 Prerequisites](#71-prerequisites)
  - [7.2 Web Application Setup](#72-web-application-setup)
  - [7.3 Hardware Assembly](#73-hardware-assembly)
  - [7.4 Firmware Flashing](#74-firmware-flashing)
  - [7.5 First Boot & WiFi Configuration](#75-first-boot--wifi-configuration)
  - [7.6 Testing Procedure](#76-testing-procedure)
- [8. Power Consumption Analysis](#8-power-consumption-analysis)
- [9. Storage Analysis](#9-storage-analysis)
- [10. Troubleshooting Guide](#10-troubleshooting-guide)
- [11. Future Improvements](#11-future-improvements)
- [12. Safety & Precautions](#12-safety--precautions)
- [13. License](#13-license)
- [14. Credits](#14-credits)

---

## 1. Project Overview

### 1.1 Problem Statement

Navigating public transport in Nepal's cities (Kathmandu, Pokhara, Bharatpur, etc.) is a nightmare:

| Current Option | Problem |
|----------------|---------|
| **Pathao / InDrive** | Expensive ride-hailing (bikes/taxis) — not affordable for daily commutes |
| **Ask strangers** | Unreliable, time-consuming, language barrier for tourists |
| **Google Maps** | Shows driving/walking paths only — has **zero knowledge** of Nepal's informal bus routes |
| **Trial and error** | Board wrong bus, overpay fares, miss stops, waste hours |

Nepal's public transport system is **completely informal** — there are no published route maps, no official timetables, no digital fare charts, and no real-time tracking. Buses run on routes known only through word-of-mouth.

### 1.2 Solution

**Sawari** builds its own **crowd-sourced public transit database** combined with **live GPS hardware on buses** to provide:

- 🗺️ **Route Finding** — Enter Point A → Point B, get the exact bus (or two buses with a transfer) that connects them
- 🚌 **Bus Details** — Vehicle name, photo, fare (with student/elderly discounts), boarding/drop-off stops
- 🗣️ **Conductor Tips** — Tells you exactly what to say: *"Tell the conductor: 'Kalanki' to 'Ratnapark'"*
- 📍 **Live Tracking** — Real-time GPS positions of buses on the map with smooth animation
- ⏱️ **ETA & Approaching** — Calculates which stop a bus is approaching and estimated arrival time
- 🌿 **Carbon Calculator** — Shows CO₂ saved by taking a bus vs. a taxi
- 🚨 **Emergency Alerts** — Strikes, road blocks, diversions shown on the map
- 👥 **Community-Driven** — Volunteer agents collect and submit real-world bus data

### 1.3 Key Objectives

1. **Democratize transit information** — Make public bus navigation accessible to everyone
2. **Reduce transport costs** — Guide users to affordable public buses instead of expensive ride-hailing
3. **Tourist accessibility** — Help foreign visitors navigate Nepal's buses with cultural tips
4. **Crowd-sourced data** — Build Nepal's first comprehensive public transit database through volunteer agents
5. **Live tracking** — Deploy low-cost IoT GPS devices on buses for real-time position data
6. **Environmental impact** — Calculate and display carbon savings from using public transport

### 1.4 Real-World Use Case

> **Scenario:** A student needs to travel from Kalanki (western Kathmandu) to Putalisadak (central Kathmandu) for an exam.
>
> 1. Opens Sawari → enters "Kalanki" as Point A, "Putalisadak" as Point B
> 2. Sawari finds **Route: Kalanki–Buspark** operated by **Sajha Yatayat**
> 3. Shows: Board at **Kalanki** stop, drop off at **Putalisadak** stop
> 4. Fare: **NPR 25** (Student discount: **NPR 20**)
> 5. Conductor tip: *"Tell the conductor: 'Kalanki' to 'Putalisadak'"*
> 6. Tourist tip: *"Say 'Roknu!' (रोक्नु) to signal your stop"*
> 7. Live map shows the Sajha bus currently near **Bagbazar** — ETA 5 minutes
> 8. After the trip, the student rates the accuracy and leaves a review

---

## 2. System Architecture

### 2.1 High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        SAWARI PLATFORM                                  │
│                                                                         │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────────────────┐   │
│  │  PUBLIC USER  │    │    AGENT     │    │       ADMIN              │   │
│  │   (Map Page)  │    │ (Dashboard)  │    │    (Dashboard)           │   │
│  │              │    │              │    │                          │   │
│  │ • Route Find │    │ • Add Stops  │    │ • Approve/Reject Data   │   │
│  │ • Live Track │    │ • Add Buses  │    │ • Manage Agents         │   │
│  │ • Fare Calc  │    │ • Add Routes │    │ • Create Alerts         │   │
│  │ • Feedback   │    │ • Leaderboard│    │ • Review Suggestions    │   │
│  └──────┬───────┘    └──────┬───────┘    └──────────┬───────────────┘   │
│         │                    │                       │                   │
│  ═══════╪════════════════════╪═══════════════════════╪═══════════════   │
│         │            REST API LAYER                  │                   │
│  ═══════╪════════════════════╪═══════════════════════╪═══════════════   │
│         │                    │                       │                   │
│  ┌──────┴────────────────────┴───────────────────────┴───────────────┐  │
│  │                     PHP BACKEND (Apache/XAMPP)                     │  │
│  │                                                                   │  │
│  │  routing-engine.php │ vehicles.php │ locations.php │ trips.php    │  │
│  │  gps-device.php     │ agents.php   │ admins.php    │ alerts.php   │  │
│  └──────────────────────────────┬────────────────────────────────────┘  │
│                                 │                                       │
│  ┌──────────────────────────────┴────────────────────────────────────┐  │
│  │                   MySQL / MariaDB (9 Tables)                      │  │
│  │  admins │ agents │ contributions │ locations │ routes             │  │
│  │  vehicles │ trips │ alerts │ suggestions                          │  │
│  └───────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │              EXTERNAL SERVICES                                    │  │
│  │  • OpenStreetMap Tiles (map rendering)                            │  │
│  │  • OSRM (road path visualization only)                           │  │
│  │  • Feather Icons (UI icons)                                       │  │
│  │  • Google Fonts (Inter typeface)                                   │  │
│  └───────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘

                              ▲
                              │ HTTP POST (JSON)
                              │ Every 5 seconds
                              │
┌─────────────────────────────┴────────────────────────────────────────┐
│                   IoT GPS HARDWARE DEVICE                            │
│                                                                      │
│  ┌───────────┐   UART   ┌───────────┐  WiFi   ┌──────────────────┐ │
│  │  NEO-6M   │ ──────── │   ESP32   │ ─────── │   Web Server     │ │
│  │   GPS     │          │           │         │  (api/gps-device) │ │
│  └───────────┘          │   ┌───────┤         └──────────────────┘ │
│                         │   │ OLED  │                               │
│  ┌───────────┐   I2C    │   │Display│                               │
│  │  SH1106   │ ──────── │   └───────┤                               │
│  │  128×64   │          │           │                               │
│  └───────────┘          │  4× LEDs  │                               │
│                         └───────────┘                               │
│  Power: 12V Vehicle → Buck Converter → 5V ESP32                     │
└──────────────────────────────────────────────────────────────────────┘
```

### 2.2 Data Flow

```
┌──────────────┐         ┌───────────────┐         ┌──────────────────┐
│  GPS         │  NMEA   │    ESP32      │  JSON   │   PHP Backend    │
│  Satellites  │ ──────► │  (Firmware)   │ ──────► │  gps-device.php  │
│  (4-12)      │  9600   │  TinyGPSPlus  │  HTTP   │  Validates +     │
│              │  baud   │  Parse & Pack │  POST   │  Updates DB      │
└──────────────┘         └───────────────┘         └────────┬─────────┘
                                                            │
                                                            ▼
┌──────────────┐         ┌───────────────┐         ┌──────────────────┐
│  User's      │  JSON   │   Leaflet.js  │  AJAX   │  vehicles.php    │
│  Browser     │ ◄────── │   Map Engine  │ ◄────── │  ?action=live    │
│  (Map Page)  │  Render │   Tracking.js │  8s     │  GPS data query  │
│              │         │  Smooth Anim  │  poll   │                  │
└──────────────┘         └───────────────┘         └──────────────────┘
```

### 2.3 Hardware–Software Interaction

| Step | Component | Action | Protocol |
|------|-----------|--------|----------|
| 1 | GPS Satellites → NEO-6M | Receive satellite signals, calculate position | RF/NMEA |
| 2 | NEO-6M → ESP32 | Send NMEA sentences (lat, lon, speed, etc.) | UART @ 9600 baud |
| 3 | ESP32 (Firmware) | Parse NMEA via TinyGPSPlus, build JSON payload | Internal |
| 4 | ESP32 → Web Server | HTTP POST JSON to `/api/gps-device.php` | WiFi / HTTP |
| 5 | PHP Backend | Validate payload, map `bus_id` → `vehicle_id`, update DB | MySQL |
| 6 | User Browser → Server | Poll `/api/vehicles.php?action=live` every 8s | AJAX / JSON |
| 7 | Leaflet.js | Render vehicle markers with smooth cubic ease-out animation | JavaScript |
| 8 | ESP32 (Offline) | If WiFi lost → queue to LittleFS → flush when reconnected | LittleFS |

---

## 3. Hardware Section

### 3.1 Component Overview

| Qty | Component | Model | Purpose | Cost (USD) |
|-----|-----------|-------|---------|------------|
| 1 | Microcontroller | ESP32 Dev Module (38-pin) | Main processor, WiFi, firmware | $5–8 |
| 1 | GPS Module | NEO-6M with ceramic antenna | Real-time positioning | $8–12 |
| 1 | OLED Display | 1.3" SH1106/SSD1306, I2C, 128×64 | Status display | $4–6 |
| 1 | Buck Converter | LM2596 or MP1584 | 12V → 5V voltage regulation | $2–4 |
| 4 | Status LEDs | 3mm/5mm (Green, Blue, Yellow, Red) | Visual indicators | $1 |
| 4 | Resistors | 220Ω, 1/4W | LED current limiting | $0.50 |
| 1 | Capacitor | 100µF 25V Electrolytic | Input filtering | $0.50 |
| 1 | Capacitor | 10µF 16V Ceramic | Output filtering | $0.30 |
| 1 | TVS Diode | P6KE18A | Voltage spike protection | $0.50 |
| 1 | Fuse + Holder | Blade type, 2A | Over-current protection | $1 |
| 1 | Enclosure | IP65 rated, ~100×68×50mm | Weather protection | $5–10 |
| — | Wires, headers, heat shrink | 22 AWG, JST connectors | Internal wiring | $3 |
| | | | **Total** | **~$30–50** |

### 3.2 Microcontroller — ESP32 Dev Module

<!-- ![ESP32 Dev Module](assets/images/hardware/esp32.jpg) -->
> 📷 *Image: ESP32 38-pin Development Board*

**Technical Specifications:**

| Parameter | Value |
|-----------|-------|
| Chip | Espressif ESP32-WROOM-32 |
| Processor | Dual-core Xtensa LX6, 240 MHz |
| Flash | 4 MB |
| SRAM | 520 KB |
| WiFi | 802.11 b/g/n, 2.4 GHz |
| Bluetooth | v4.2 BR/EDR + BLE |
| GPIO Pins | 38 total (25 usable) |
| UART | 3 channels (UART0 for debug, UART2 for GPS) |
| I2C | 2 channels (used for OLED) |
| ADC | 18 channels, 12-bit |
| Operating Voltage | 3.3V (5V via VIN) |
| Operating Temperature | -40°C to +85°C |

**Why ESP32 was chosen:**
- Built-in WiFi — no separate WiFi module needed
- Dual-core allows GPS parsing on one core and WiFi operations on another
- Multiple UART channels for GPS communication
- Built-in I2C for OLED display
- LittleFS support for offline data storage
- WatchDog Timer for automotive reliability
- Abundant GPIO for LEDs and buttons
- Low cost (~$5) and widely available in Nepal

**Power Requirements:**
- Input: 5V via VIN pin (from buck converter)
- Typical current: 80 mA (WiFi active)
- Peak current: 240 mA (WiFi transmitting)

### 3.3 GPS Module — NEO-6M

<!-- ![NEO-6M GPS Module](assets/images/hardware/neo6m.jpg) -->
> 📷 *Image: NEO-6M GPS Module with Ceramic Antenna*

**Technical Specifications:**

| Parameter | Value |
|-----------|-------|
| Manufacturer | u-blox |
| Chipset | u-blox 6 positioning engine |
| Channels | 50 (tracking), 22 (acquisition) |
| Position Accuracy | 2.5m CEP (open sky) |
| Velocity Accuracy | 0.1 m/s |
| Update Rate | 1 Hz (default) |
| Cold Start | ~27 seconds |
| Hot Start | ~1 second |
| Communication | UART Serial (TTL 3.3V) |
| Default Baud Rate | 9600 bps (8N1) |
| Operating Voltage | 2.7–3.6V |
| Operating Current | 35 mA typical, 50 mA peak |
| Antenna | Built-in ceramic patch |

**Why NEO-6M was chosen:**
- Proven reliability in automotive applications
- 2.5m accuracy sufficient for bus stop proximity detection
- Low power consumption (35 mA)
- Simple UART interface — no complex SPI bus
- Low cost (~$8) and widely available
- Well-documented with extensive community support
- Compatible with TinyGPSPlus library

**NMEA Output:**
The module outputs standard NMEA 0183 sentences including `$GPGGA` (position fix), `$GPRMC` (recommended minimum), `$GPGSA` (satellite info), and `$GPGSV` (satellites in view).

### 3.4 OLED Display — 1.3" SH1106

<!-- ![OLED Display](assets/images/hardware/oled.jpg) -->
> 📷 *Image: 1.3" SH1106 OLED Display Module*

**Technical Specifications:**

| Parameter | Value |
|-----------|-------|
| Size | 1.3 inches diagonal |
| Resolution | 128 × 64 pixels |
| Controller | SH1106 (or SSD1306 compatible) |
| Interface | I2C (address: 0x3C or 0x3D) |
| Colors | Monochrome (white/blue/yellow variants) |
| Operating Voltage | 3.3V – 5V |
| Current Draw | 20 mA typical |
| Viewing Angle | >160° |

**Why this display was chosen:**
- I2C interface uses only 2 pins (SDA, SCL)
- High contrast, readable in sunlight
- Low power consumption (20 mA)
- Compact form factor fits in enclosure
- U8g2 library provides rich graphics API

**Display Screens:**
1. **Boot splash** — SAWARI logo with progress bar
2. **WiFi setup** — AP name and portal instructions
3. **GPS searching** — Satellite count, WiFi status
4. **Main telemetry** — Lat/Lon, speed, direction, WiFi SSID, queue count
5. **Portal active** — AP name and IP for configuration

### 3.5 Power System

The device is powered from a 12V vehicle electrical system:

```
Vehicle +12V ── [2A Fuse] ── [TVS P6KE18A] ──┬── [100µF Cap] ── Buck Converter IN+
                                              │
Vehicle GND ──────────────────────────────────┴──────────── Buck Converter IN-
                                                                      │
                                                              Adjust to 5.0V
                                                                      │
                                                           Buck OUT+ ── ESP32 VIN
                                                           Buck OUT- ── ESP32 GND
```

**Buck Converter (LM2596 / MP1584):**

| Parameter | Value |
|-----------|-------|
| Input Voltage | 7–35V DC (handles 12V with headroom) |
| Output Voltage | 5.0V ±0.1V (adjustable via potentiometer) |
| Max Output Current | 3A (system draws ~360 mA max) |
| Efficiency | ~85–92% |

**Power Consumption Breakdown:**

| Component | Typical (mA) | Peak (mA) |
|-----------|-------------|-----------|
| ESP32 (WiFi active) | 80 | 240 |
| NEO-6M GPS | 35 | 50 |
| OLED Display (SH1106) | 20 | 30 |
| LEDs (all on, 4×10mA) | 40 | 40 |
| **Total** | **175** | **360** |

### 3.6 LED Status Indicators

| LED | Color | GPIO | Meaning |
|-----|-------|------|---------|
| Power | Green | GPIO2 | Solid ON = Device powered and running |
| WiFi | Blue | GPIO4 | Solid ON = WiFi connected; OFF = Disconnected |
| GPS | Yellow | GPIO13 | Solid ON = Valid GPS fix; OFF = Searching |
| Data | Red | GPIO14 | Blinks 150ms = Data sent to server successfully |

**Quick Status Reference:**

| Power | WiFi | GPS | Data | System State |
|-------|------|-----|------|-------------|
| ● | ● | ● | ◐ | Normal operation (online, GPS locked) |
| ● | ○ | ● | ○ | Offline mode (queuing data locally) |
| ● | ● | ○ | ○ | GPS searching (WiFi ready) |
| ● | ○ | ○ | ○ | Startup / connecting |

> ● = ON, ○ = OFF, ◐ = Blinking

### 3.7 Complete Pin Mapping Table

| GPIO | Function | Connected To | Wire Color | Notes |
|------|----------|-------------|------------|-------|
| VIN | 5V Power | Buck Converter OUT+ | Red | Regulated 5V from vehicle |
| GND | Ground | Common Ground Bus | Black | Star ground topology |
| 3V3 | 3.3V Out | GPS VCC, OLED VCC | Orange | From ESP32's LDO regulator |
| GPIO16 | UART2 RX | GPS TX | Yellow | GPS data receive |
| GPIO17 | UART2 TX | GPS RX | Green | GPS configuration (optional) |
| GPIO21 | I2C SDA | OLED SDA | Blue | Display data line |
| GPIO22 | I2C SCL | OLED SCL | Purple | Display clock line |
| GPIO2 | Digital OUT | Power LED (Green) via 220Ω | White | Built-in LED on most boards |
| GPIO4 | Digital OUT | WiFi LED (Blue) via 220Ω | White | WiFi status indicator |
| GPIO13 | Digital OUT | GPS LED (Yellow) via 220Ω | White | GPS lock indicator |
| GPIO14 | Digital OUT | Data LED (Red) via 220Ω | White | Transmission blink |
| GPIO0 | Digital IN | BOOT Button (built-in) | — | Long-press = WiFi portal |

### 3.8 Wiring Diagram

```
                                    ┌─────────────────────────────────┐
                                    │         ESP32 Dev Module        │
                                    │                                 │
    ┌──────────────┐                │    3V3 ●           ● VIN (5V)   │
    │  12V Vehicle │                │    GND ●           ● GND        │
    │    Power     │                │   GPIO16 ●         ● GPIO2      │── LED (Power/Green)
    └──────┬───────┘                │   GPIO17 ●         ● GPIO4      │── LED (WiFi/Blue)
           │                        │   GPIO21 ●         ● GPIO13     │── LED (GPS/Yellow)
           │ +12V                   │   GPIO22 ●         ● GPIO14     │── LED (Data/Red)
    ┌──────┴───────┐                │                                 │
    │   2A Fuse    │                └────────────────┬────────────────┘
    └──────┬───────┘                                 │
           │                                         │
    ┌──────┴───────┐                    ┌────────────┴────────────┐
    │  TVS Diode   │                    │    Buck Converter       │
    │  (P6KE18A)   │                    │    (LM2596 / MP1584)    │
    └──────┬───────┘                    │                         │
           │                            │   IN+ ──── OUT+ ──→ ESP32 VIN
           │ ──────────────────────────→│                         │
           │                            │   IN- ──── OUT- ──→ ESP32 GND
    Vehicle GND ───────────────────────→│   [Adjust to 5V]       │
                                        └─────────────────────────┘

    ┌─────────────────┐                  ┌─────────────────┐
    │   NEO-6M GPS    │                  │    1.3" OLED    │
    │                 │                  │    (SH1106)     │
    │   VCC ──────────│── 3.3V           │   VCC ──────────│── 3.3V
    │   GND ──────────│── GND            │   GND ──────────│── GND
    │   TX  ──────────│── GPIO16 (RX2)   │   SDA ──────────│── GPIO21
    │   RX  ──────────│── GPIO17 (TX2)   │   SCL ──────────│── GPIO22
    └─────────────────┘                  └─────────────────┘
```

### 3.9 Protection Circuit

| Automotive Challenge | Solution |
|---------------------|----------|
| Voltage spikes (load dump up to 40V) | TVS diode (P6KE18A) clamping |
| Voltage sag during cranking (6–8V) | Buck converter handles wide input range |
| Reverse polarity | 2A fuse (blows safely) |
| Electrical noise from ignition | Input/output capacitors (100µF + 10µF) |
| Over-current | 2A blade fuse protection |

### 3.10 Component Alternatives

| Component | Current Choice | Alternative 1 | Alternative 2 |
|-----------|---------------|---------------|---------------|
| Microcontroller | ESP32 Dev Module | ESP32-S3 (USB-C native) | ESP32-C3 (lower power, RISC-V) |
| GPS Module | NEO-6M | NEO-M8N (GLONASS + GPS) | Quectel L80 (built-in patch antenna) |
| Display | SH1106 1.3" OLED | SSD1306 0.96" OLED | ST7735 1.44" TFT (color) |
| Buck Converter | LM2596 | MP1584 (smaller footprint) | XL4015 (higher current capacity) |
| Communication | WiFi only | SIM800L (GSM/GPRS cellular) | LoRa SX1276 (long range, low power) |
| Storage | LittleFS (internal flash) | MicroSD card (SPI) | EEPROM (small structured data) |

---

## 4. Software Section

### 4.1 Tech Stack

| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| **Frontend** | HTML5, CSS3, Vanilla JavaScript | — | User interface (no frameworks) |
| **Backend** | PHP | 7.4+ | REST API, server-side logic |
| **Database** | MySQL / MariaDB | 5.7+ / 10.3+ | Persistent data storage |
| **Maps** | Leaflet.js | 1.9.4 | Interactive map rendering |
| **Tiles** | OpenStreetMap | — | Map tile provider |
| **Road Paths** | OSRM | Public demo | Visual road-following polylines |
| **Icons** | Feather Icons | — | SVG icon set |
| **Font** | Inter (Google Fonts) | — | Typography |
| **Server** | Apache | Via XAMPP | HTTP server |
| **Firmware** | Arduino C++ | ESP32 Core 2.x+ | IoT device firmware |
| **IDE** | Arduino IDE | 2.x | Firmware development |

### 4.2 Folder Structure

```
SAWARI/
├── index.php                   ← Landing page (project intro, navigation)
├── setup.php                   ← One-click database installer & seed data
├── schema.sql                  ← Complete database schema (9 tables)
├── test-data.sql               ← Sample data for testing
├── README.md                   ← This file
├── vision.md                   ← Product vision & architecture
├── workflow.md                 ← Development workflow record
│
├── api/                        ← REST API layer
│   ├── config.php              ← DB connection (PDO), constants, helpers
│   ├── routing-engine.php      ← Route resolution + fare calculation
│   ├── gps-device.php          ← Hardware GPS receiver endpoint
│   ├── locations.php           ← CRUD + search for bus stops
│   ├── routes.php              ← CRUD for route management
│   ├── vehicles.php            ← CRUD + live GPS data + simulator
│   ├── trips.php               ← Trip logging, ratings, reviews
│   ├── contributions.php       ← Contribution management
│   ├── alerts.php              ← Alert CRUD (route disruptions)
│   ├── suggestions.php         ← Community suggestion submissions
│   ├── agents.php              ← Agent auth + profile management
│   └── admins.php              ← Admin auth + management
│
├── assets/
│   ├── css/
│   │   ├── global.css          ← Design tokens, reset, typography, layout utilities
│   │   ├── components.css      ← Reusable UI (buttons, cards, forms, modals, toasts)
│   │   ├── map.css             ← User map page + responsive + marker styles
│   │   ├── agent.css           ← Agent dashboard styles
│   │   └── admin.css           ← Admin dashboard styles
│   ├── js/
│   │   ├── map.js              ← Leaflet init, SVG markers, route polylines
│   │   ├── search.js           ← Point A/B input, autocomplete, geocoding
│   │   ├── routing.js          ← Route result display, fare cards, conductor tips
│   │   ├── tracking.js         ← Live vehicle tracking (8s poll, smooth animation)
│   │   ├── agent.js            ← Agent dashboard logic
│   │   └── admin.js            ← Admin dashboard logic
│   └── images/
│       └── vehicles/           ← Vehicle reference images
│
├── pages/
│   ├── map.php                 ← Full-screen public map (main user page)
│   ├── agent/
│   │   ├── login.php           ← Agent login / registration
│   │   ├── dashboard.php       ← Agent stats, leaderboard
│   │   ├── add-location.php    ← Pin bus stops on map
│   │   ├── add-vehicle.php     ← Register vehicles with photos
│   │   ├── add-route.php       ← Build ordered stop sequences
│   │   └── my-contributions.php← Contribution history + status
│   └── admin/
│       ├── login.php           ← Admin authentication
│       ├── dashboard.php       ← System overview stats
│       ├── manage-locations.php← Approve/reject bus stops
│       ├── manage-vehicles.php ← Approve/reject vehicles
│       ├── manage-routes.php   ← Approve/reject routes
│       ├── manage-agents.php   ← Agent account management
│       ├── manage-alerts.php   ← Create/resolve disruption alerts
│       ├── contributions.php   ← Unified pending review queue
│       └── suggestions.php     ← Community suggestion inbox
│
├── includes/
│   ├── admin-header.php        ← Admin page header + nav
│   ├── admin-footer.php        ← Admin page footer + scripts
│   ├── agent-header.php        ← Agent page header + nav
│   ├── agent-footer.php        ← Agent page footer + scripts
│   ├── auth-admin.php          ← Admin session guard
│   └── auth-agent.php          ← Agent session guard
│
├── tools/
│   └── gps-simulator.php       ← GPS testing tool (simulates bus movement)
│
├── logs/
│   └── gps-device.json         ← Rolling GPS hardware log (500 entries max)
│
├── uploads/
│   └── vehicles/               ← User-uploaded vehicle images
│
├── gallery/                    ← Project screenshots / demo images
│
└── sawari_telemetry/           ← ESP32 Firmware (Arduino project)
    ├── sawari_telemetry.ino    ← Main firmware entry point
    ├── config.h                ← All configuration constants
    ├── gps_handler.h/cpp       ← GPS module interface (TinyGPSPlus)
    ├── network_handler.h/cpp   ← WiFi + HTTP POST management
    ├── storage_handler.h/cpp   ← LittleFS offline queue
    ├── display_handler.h/cpp   ← OLED display screens (U8g2)
    ├── led_handler.h/cpp       ← LED status management
    ├── README.md               ← Hardware assembly guide
    └── GPS_TELEMETRY_DOCUMENTATION.md ← GPS technical deep-dive
```

### 4.3 Code Architecture

The project follows a **modular, layered architecture**:

```
┌─────────────────────────────────────────────┐
│             PRESENTATION LAYER              │
│  HTML/CSS/JS pages, Leaflet map, UI logic   │
├─────────────────────────────────────────────┤
│              API LAYER (REST)               │
│  PHP endpoints handling AJAX requests       │
│  Action-based routing (?action=xxx)         │
├─────────────────────────────────────────────┤
│             BUSINESS LOGIC                  │
│  Routing engine, fare calc, validation      │
│  GPS data processing, geofencing            │
├─────────────────────────────────────────────┤
│             DATA ACCESS LAYER               │
│  PDO (prepared statements), JSON fields     │
│  Singleton DB connection pattern            │
├─────────────────────────────────────────────┤
│             DATABASE (MySQL)                │
│  9 normalized tables with indexes           │
│  JSON columns for flexible data             │
└─────────────────────────────────────────────┘
```

**Design Principles:**
- **No external PHP frameworks** — pure PHP for transparency and minimal dependencies
- **No JavaScript frameworks** — vanilla JS with clean module separation
- **Database-driven routing** — all route logic comes from stored data, not computed paths
- **OSRM is visual only** — used solely for drawing realistic road polylines on the map
- **Approval workflow** — all crowd-sourced data requires admin review before public visibility
- **Stateless API** — each request is self-contained; auth via PHP sessions

### 4.4 Module-Wise Explanation

#### Frontend Modules

| Module | File | Purpose |
|--------|------|---------|
| **Map Engine** | `map.js` | Initializes Leaflet map centered on Kathmandu (27.7172, 85.3240), renders custom SVG markers (teardrop pins, circle dots), manages route polylines |
| **Search** | `search.js` | Handles Point A/B input with autocomplete from locations API, geocoding, trip logging, session management |
| **Routing** | `routing.js` | Displays route results — fare cards, vehicle photos, intermediate stops, conductor tips, carbon savings |
| **Tracking** | `tracking.js` | Polls live vehicle positions every 8 seconds, animates markers with `requestAnimationFrame` + cubic ease-out (2s interpolation), calculates ETA |

#### Backend Modules

| Module | File | Purpose |
|--------|------|---------|
| **Config** | `config.php` | PDO singleton, security headers, response helpers, pagination, input sanitization |
| **Routing Engine** | `routing-engine.php` | Finds nearest stops via Haversine formula, checks `location_list` JSON for route matching, handles direct and transfer routes |
| **GPS Device** | `gps-device.php` | Receives hardware GPS payloads, validates Nepal bounding box, maps `bus_id` → `vehicle_id`, logs to rolling JSON file |
| **Vehicles** | `vehicles.php` | CRUD operations, live GPS query (`gps_active=1` within 2 min), image upload handling |
| **Locations** | `locations.php` | CRUD for bus stops with geo-search, nearby duplicate detection (< 300m warning) |
| **Routes** | `routes.php` | CRUD for bus routes, `location_list` JSON management |
| **Trips** | `trips.php` | Trip logging, feedback (1–5 rating, accuracy, review), departure/destination counter increment |
| **Alerts** | `alerts.php` | CRUD for route disruption alerts with severity levels |

#### Firmware Modules

| Module | Files | Purpose |
|--------|-------|---------|
| **GPS Handler** | `gps_handler.h/cpp` | UART2 serial interface, TinyGPSPlus parsing, telemetry struct, JSON payload builder |
| **Network Handler** | `network_handler.h/cpp` | WiFiManager integration, auto-connect, captive portal, HTTP POST, RSSI monitoring |
| **Storage Handler** | `storage_handler.h/cpp` | LittleFS queue (JSONL format), enqueue/flush/clear, max 500 records, FIFO |
| **Display Handler** | `display_handler.h/cpp` | U8g2 OLED screens: boot splash, WiFi setup, GPS search, telemetry, portal |
| **LED Handler** | `led_handler.h/cpp` | 4-LED state management, blink timing, non-blocking updates |

### 4.5 Database Schema

9 normalized tables with proper indexes:

```sql
┌──────────────────────────────────────────────────────────┐
│                    DATABASE: sawari                        │
├──────────────┬───────────────────────────────────────────┤
│   admins     │ System administrators (superadmin/mod)     │
│   agents     │ Volunteer data collectors (points, rank)   │
│   contributions │ Every agent submission + approval status│
│   locations  │ Bus stops/landmarks with GPS coordinates   │
│   routes     │ Named bus routes with ordered stop JSON    │
│   vehicles   │ Buses with images, routes, live GPS        │
│   trips      │ User journey logs with feedback            │
│   alerts     │ Route disruption warnings                  │
│   suggestions│ Community improvement ideas                │
└──────────────┴───────────────────────────────────────────┘
```

**Key Tables in Detail:**

#### `admins`
| Column | Type | Description |
|--------|------|-------------|
| admin_id | INT AUTO_INCREMENT PK | Unique ID |
| name | VARCHAR(255) | Full name |
| email | VARCHAR(255) UNIQUE | Login email |
| password | VARCHAR(255) | bcrypt hash |
| role | ENUM('superadmin', 'moderator') | Permission level |
| status | ENUM('active', 'inactive') | Account status |

#### `agents`
| Column | Type | Description |
|--------|------|-------------|
| agent_id | INT PK | Unique ID |
| name | VARCHAR(255) | Full name |
| email | VARCHAR(255) UNIQUE | Login email |
| password | VARCHAR(255) | bcrypt hash |
| points | INT DEFAULT 0 | Leaderboard score |
| contributions_count | INT DEFAULT 0 | Total submissions |
| approved_count | INT DEFAULT 0 | Approved submissions |
| status | ENUM('active', 'suspended', 'inactive') | Account status |

#### `locations`
| Column | Type | Description |
|--------|------|-------------|
| location_id | INT PK | Unique ID |
| name | VARCHAR(255) | Stop name (e.g., "Kalanki") |
| latitude | DECIMAL(10,8) | GPS latitude |
| longitude | DECIMAL(11,8) | GPS longitude |
| type | ENUM('stop', 'landmark') | Location category |
| status | ENUM('pending', 'approved', 'rejected') | Review status |
| departure_count | INT DEFAULT 0 | Times used as trip origin |
| destination_count | INT DEFAULT 0 | Times used as trip destination |

#### `routes`
| Column | Type | Description |
|--------|------|-------------|
| route_id | INT PK | Unique ID |
| name | VARCHAR(255) | Route name (e.g., "Kalanki–Buspark") |
| location_list | JSON | Ordered array of stop objects with coords |
| fare_base | DECIMAL(6,2) | Base fare in NPR |
| fare_per_km | DECIMAL(6,2) | Per-kilometer rate |
| status | ENUM('pending', 'approved', 'rejected') | Review status |

#### `vehicles`
| Column | Type | Description |
|--------|------|-------------|
| vehicle_id | INT PK | Unique ID |
| name | VARCHAR(255) | Vehicle/company name |
| image_path | VARCHAR(255) | Uploaded photo path |
| electric | TINYINT(1) | Is electric vehicle flag |
| used_routes | JSON | Array of assigned route IDs |
| latitude/longitude | TEXT | Live GPS position |
| velocity | TEXT | Current speed (km/h) |
| gps_active | TINYINT(1) | Currently sending GPS data |
| last_gps_update | DATETIME | Last GPS ping timestamp |

#### `trips`
| Column | Type | Description |
|--------|------|-------------|
| trip_id | INT PK | Unique ID |
| session_id | VARCHAR(64) | Anonymous user session |
| route_id / vehicle_id | INT FK | Route and vehicle used |
| boarding_stop_id / destination_stop_id | INT FK | Board and drop-off stops |
| transfer_stop_id | INT FK | Transfer point (if needed) |
| rating | TINYINT | 1–5 stars |
| accuracy_feedback | ENUM | accurate / slightly_off / inaccurate |
| fare_paid | DECIMAL(6,2) | Actual fare paid |
| carbon_saved | DECIMAL(8,4) | CO₂ savings (kg) |

### 4.6 API Endpoints

All APIs follow action-based routing via query parameter `?action=xxx`:

| Endpoint | Actions | Method | Description |
|----------|---------|--------|-------------|
| `/api/routing-engine.php` | `find-route` | GET | Core route resolution (origin/dest coords) |
| `/api/locations.php` | `list`, `search`, `get`, `add`, `update`, `delete` | GET/POST | Bus stop CRUD + geo-search |
| `/api/routes.php` | `list`, `get`, `add`, `update`, `delete` | GET/POST | Route CRUD |
| `/api/vehicles.php` | `list`, `get`, `add`, `update`, `delete`, `live` | GET/POST | Vehicle CRUD + live GPS |
| `/api/trips.php` | `log`, `feedback`, `list` | GET/POST | Trip logging + ratings |
| `/api/contributions.php` | `list`, `review` | GET/POST | Contribution management |
| `/api/alerts.php` | `list`, `get`, `create`, `resolve` | GET/POST | Alert CRUD |
| `/api/suggestions.php` | `list`, `submit`, `review` | GET/POST | Suggestion management |
| `/api/agents.php` | `register`, `login`, `logout`, `profile` | GET/POST | Agent auth |
| `/api/admins.php` | `login`, `logout`, `profile`, `list` | GET/POST | Admin auth |
| `/api/gps-device.php` | — (POST body) | POST | Hardware GPS data receiver |

**Example API Call — Route Finding:**
```bash
GET /api/routing-engine.php?action=find-route
    &origin_lat=27.6933&origin_lng=85.2814
    &dest_lat=27.7050&dest_lng=85.3150
```

**Example API Response:**
```json
{
  "success": true,
  "type": "direct",
  "results": [
    {
      "route": { "route_id": 1, "name": "Kalanki - Buspark" },
      "vehicle": { "name": "Sajha Yatayat", "image_path": "sajha.jpg", "electric": 1 },
      "boarding_stop": { "name": "Kalanki", "lat": 27.6933, "lng": 85.2814 },
      "destination_stop": { "name": "Putalisadak", "lat": 27.7050, "lng": 85.3150 },
      "intermediate_stops": ["RNAC", "Bagbazar"],
      "fare": 25,
      "student_fare": 20,
      "conductor_tip": "Tell the conductor: 'Kalanki' to 'Putalisadak'",
      "carbon_saved": 0.145,
      "estimated_wait": "10-15 min"
    }
  ]
}
```

### 4.7 Routing Engine

The routing algorithm is **entirely database-driven** — no arbitrary road routing:

**Direct Route Resolution:**
```
1. User enters Point A (lat, lng) and Point B (lat, lng)
2. Find approved bus stops within 2 km of Point A → nearOrigin[]
3. Find approved bus stops within 2 km of Point B → nearDest[]
4. Load all approved routes with their location_list JSON
5. For each route:
   a. Check if any nearOrigin stop exists in this route's location_list
   b. Check if any nearDest stop exists in this route's location_list
   c. Verify direction: boarding_index < destination_index
   d. If match → calculate fare, build result
6. Sort results by total walking distance
7. Return top 3 options
```

**Transfer Route Resolution:**
```
1. Collect all routes passing near Point A → routesA[]
2. Collect all routes passing near Point B → routesB[]
3. For each pair (routeA, routeB):
   a. Find intersection stops (locations on BOTH routes)
   b. Evaluate total distance for each transfer point
4. Return best transfer option with full details for both legs
```

### 4.8 Fare Calculation

Nepal's public transport fare rules:

```php
$fare = $fare_base + ($fare_per_km × $distance_km);
$fare = max(20, round($fare / 5) * 5);              // Round to ×5 NPR, minimum 20
$studentFare = max(15, round($fare * 0.75 / 5) * 5); // 75% discount, minimum 15
```

| Parameter | Value |
|-----------|-------|
| Base fare | Stored per route (`fare_base` column) |
| Per-km rate | Stored per route (`fare_per_km` column) |
| Rounding | Nearest multiple of 5 NPR |
| Minimum fare | NPR 20 (standard), NPR 15 (student/elderly) |
| Student/elderly discount | 75% of standard fare |
| Distance calculation | Haversine formula between consecutive stops |

**Carbon Emission Comparison:**

| Transport Mode | CO₂ per km | Source |
|---------------|------------|--------|
| Regular bus | 0.089 kg | Per-passenger emission |
| Electric bus | 0.020 kg | Green energy calculation |
| Car/taxi | 0.210 kg | Average vehicle emission |

### 4.9 Error Handling Strategy

**Backend (PHP):**
- PDO exceptions with `ERRMODE_EXCEPTION`
- All user inputs sanitized via `htmlspecialchars()` and prepared statements
- Structured JSON error responses: `{"success": false, "message": "...", "type": "error_code"}`
- HTTP status codes: 200 (success), 400 (bad request), 401 (unauthorized), 404 (not found), 500 (server error)
- Error logging via `error_log()` for server-side debugging

**Frontend (JavaScript):**
- Try-catch on all `fetch()` calls
- Graceful fallbacks: "No routes found near your location" instead of empty screens
- Loading spinners during API calls
- Toast notifications for success/error feedback

**Firmware (C++):**
- Hardware watchdog (30s timeout) — automatic restart on hang
- GPS watchdog (10 min) — restart if no GPS fix
- WiFi auto-reconnect with cooldown (10s interval)
- LittleFS offline queue — no data loss during WiFi outages
- HDOP quality filtering — skip unreliable GPS data
- Brown-out detection built into ESP32

### 4.10 Security Implementation

| Measure | Implementation |
|---------|---------------|
| SQL Injection | PDO prepared statements everywhere |
| XSS | `htmlspecialchars()` on all output |
| CSRF | Session-based tokens |
| Password Storage | `password_hash()` with bcrypt |
| Security Headers | `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy` |
| Session Management | PHP native sessions with secure configuration |
| Auth Guards | Include-based session checks on all protected pages |
| GPS Validation | Nepal bounding box check, HDOP quality filter |
| File Upload | Extension whitelist, size limits, path sanitization |

---

## 5. Firmware Features

### 5.1 Feature List

| # | Feature | Description |
|---|---------|-------------|
| 1 | Real-time GPS Tracking | 1 Hz position updates via NEO-6M with TinyGPSPlus parsing |
| 2 | WiFi Data Transmission | HTTP POST JSON payload to server every 5 seconds |
| 3 | Offline Data Buffering | LittleFS queue (up to 500 records) when WiFi unavailable |
| 4 | Auto WiFi Reconnection | 10-second interval reconnection attempts |
| 5 | Captive Portal | First-boot WiFi config via "SAWARI_SETUP" access point |
| 6 | On-Demand Portal | Long-press BOOT button (2s) to reconfigure WiFi anytime |
| 7 | OLED Status Display | 5 different screens covering all operational states |
| 8 | 4-LED Status System | Power, WiFi, GPS, Data visual indicators |
| 9 | Hardware Watchdog | 30s task WDT + 10-min GPS watchdog with auto-restart |
| 10 | Non-Blocking Loop | All tasks run on interval timers — no `delay()` blocking |
| 11 | GPS Quality Assessment | HDOP monitoring, satellite count tracking, quality display |
| 12 | Automatic Queue Flush | Offline data auto-syncs when WiFi reconnects |

### 5.2 Boot Sequence & UI Flow

```
Power ON
   │
   ├── Serial: Banner (version, bus ID, API endpoint)
   ├── LED: All OFF
   ├── OLED: SAWARI splash screen with logo
   │
   ├── [20%] Mount LittleFS storage
   ├── [40%] Initialize GPS on UART2 @ 9600 baud
   ├── [60%] WiFi connect (WiFiManager)
   │     ├── Saved credentials → Auto-connect (3s)
   │     └── No credentials → Start AP "SAWARI_SETUP" (3 min timeout)
   ├── [90%] Show WiFi result (connected SSID / offline mode)
   ├── [100%] Configure hardware watchdog (30s)
   │
   └── Enter Main Loop (non-blocking task scheduler)
         ├── TASK 1: GPS data feed (continuous)
         ├── TASK 2: BOOT button monitor (continuous)
         ├── TASK 3: WiFi portal processing (if active)
         ├── TASK 4: Telemetry send (every 5s)
         ├── TASK 5: OLED display update (every 500ms)
         ├── TASK 6: WiFi check + auto-reconnect (every 10s)
         ├── TASK 7: Offline queue flush (every 15s)
         ├── TASK 8: LED + animation update (continuous)
         └── TASK 9: GPS watchdog check (continuous)
```

### 5.3 Display UI Design

**Screen 1: Boot Splash (Progress)**
```
┌────────────────────────┐
│    🚍 S A W A R I      │
│   Bus Telemetry v2.0   │
│                        │
│  [████████░░░░░] 60%   │
│  "Connecting WiFi..."  │
└────────────────────────┘
```

**Screen 2: Main Telemetry (GPS Fix + WiFi)**
```
┌────────────────────────┐
│ ☰ SAWARI    WiFi:▓▓▓▓ │
│ Lat:  27.712345        │
│ Lon:  85.312345        │
│ Spd:  34.5 km/h  NW   │
│ SSID: HomeWiFi  Q:0   │
│ Sat:9  HDOP:0.9  SEND │
└────────────────────────┘
```

**Screen 3: GPS Searching**
```
┌────────────────────────┐
│  🛰 Searching GPS...   │
│                        │
│  Satellites: 3/4+      │
│  WiFi: Connected       │
│  SSID: HomeWiFi        │
│  Queue: 12 pending     │
└────────────────────────┘
```

**Screen 4: WiFi Portal Active**
```
┌────────────────────────┐
│  📡 WiFi Setup Mode    │
│                        │
│  Connect to:           │
│  "SAWARI_SETUP"        │
│  Open: 192.168.4.1     │
│  Select WiFi network   │
└────────────────────────┘
```

### 5.4 Data Logging System

**JSON Payload (sent every 5 seconds):**
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

| Field | Type | Unit | Description |
|-------|------|------|-------------|
| `bus_id` | int | — | Unique bus identifier (from `config.h`) |
| `latitude` | float | degrees | Decimal latitude, 6 decimal places |
| `longitude` | float | degrees | Decimal longitude, 6 decimal places |
| `speed` | float | km/h | Speed over ground |
| `direction` | float | degrees | Course relative to true north (0–360°) |
| `altitude` | float | meters | Height above mean sea level |
| `satellites` | int | count | Number of satellites in position fix |
| `hdop` | float | — | Horizontal Dilution of Precision (quality) |
| `timestamp` | string | ISO 8601 | UTC date/time from GPS |

**Server-side logging:** Rolling JSON log (`logs/gps-device.json`) stores last 500 GPS entries for debugging.

### 5.5 WiFi Handling Logic

```
┌──────────────────────────────────────────────┐
│              WiFi State Machine               │
│                                               │
│  BOOT → WiFiManager.autoConnect()            │
│    ├── Credentials saved? → Try connect       │
│    │     ├── Success → ONLINE MODE            │
│    │     └── Fail → Start AP (3 min timeout)  │
│    └── No credentials → Start AP              │
│                                               │
│  ONLINE MODE:                                 │
│    └── WiFi lost? → OFFLINE MODE              │
│          └── Check every 10s for reconnect    │
│                ├── Reconnect success:          │
│                │     → Flush queue             │
│                │     → Return to ONLINE        │
│                └── Still down → stay OFFLINE   │
│                                               │
│  BOOT BUTTON (long-press 2s):                 │
│    → Open captive portal on demand            │
│    → Configure new WiFi credentials           │
│    → Auto-close on successful connection      │
└──────────────────────────────────────────────┘
```

### 5.6 Offline Data Storage Logic

- **Technology:** LittleFS (journaling filesystem — power-loss safe)
- **Format:** JSONL (one JSON record per line in `/queue.jsonl`)
- **Capacity:** 500 records maximum (~100 KB)
- **Overflow:** Oldest records discarded (FIFO)
- **Flush triggers:** Every 15 seconds when WiFi available; also triggers on WiFi reconnect
- **Partial flush:** If flush fails mid-way, remaining records kept for next attempt

### 5.7 Error Detection System

| Error | Detection | Response |
|-------|-----------|----------|
| GPS no fix | `gpsHasFix()` returns false | Display "Searching GPS...", skip data send |
| GPS fix stale | `age() > 5000ms` | Treat as no fix |
| GPS watchdog | No fix for 10 minutes | Restart ESP32 via `ESP.restart()` |
| WiFi disconnected | `networkIsConnected()` = false | Switch to offline mode, queue data locally |
| HTTP POST failed | Response code ≠ 2xx | Queue failed record for retry |
| LittleFS mount failed | `storageInit()` returns false | Display warning, continue without storage |
| Task watchdog | `esp_task_wdt_reset()` missed for 30s | Hardware panic restart |
| Poor GPS quality | HDOP > 5.0 | Mark data as lower quality, display warning |

---

## 6. Web Interface

### 6.1 Public User Map

The main user-facing page is a **full-screen interactive map**:

**Features:**
- 🗺️ Full-screen Leaflet map centered on Kathmandu
- 📍 Custom SVG markers (teardrop pins for endpoints, circle dots for stops)
- 🔍 Point A / Point B search with autocomplete
- 🚌 Route visualization with OSRM road-following polylines
- 💰 Fare card with standard and student/elderly pricing
- 🗣️ Conductor tip: *"Tell the conductor: '[From]' to '[To]'"*
- 🚶 Walking directions (A → boarding stop, drop-off → B)
- 📊 Carbon emission comparison (bus vs. car/taxi)
- ⏱️ Estimated wait time
- 🚨 Active emergency alerts displayed on map
- 🔴 Live bus markers with smooth animation (8s polling, cubic ease-out)
- 📱 Fully responsive (768px / 640px / 380px breakpoints + landscape + safe-area)

**Tourist Help Mode:**
- What to say to the conductor when boarding
- How to signal your stop: *"Roknu!" (रोक्नु) = Stop!*
- Safety precautions for crowded buses
- Peak hour warnings (8–10 AM, 4–6 PM)

### 6.2 Agent Dashboard

Volunteer data collection portal:

| Feature | Description |
|---------|-------------|
| **Dashboard** | Contribution stats, leaderboard rank, points |
| **Add Location** | Pin bus stops on Leaflet map with GPS support. Shows existing approved stops toggle. Auto-warns if duplicate within 300m |
| **Add Vehicle** | Register buses with name, description, image upload, route assignment, electric flag, service hours |
| **Add Route** | Build routes by selecting ordered stops on map → generates `location_list` JSON |
| **My Contributions** | View all submissions with status (pending/approved/rejected) and rejection reasons |

### 6.3 Admin Dashboard

System administration panel:

| Feature | Description |
|---------|-------------|
| **Dashboard** | System overview — total locations, routes, vehicles, agents, trips, pending items |
| **Manage Locations** | Review pending stops, view on map, approve/reject |
| **Manage Vehicles** | Review pending vehicles, view images, approve/reject |
| **Manage Routes** | Review pending routes, visualize on map, approve/reject |
| **Manage Agents** | View agent profiles, contribution history, suspend/activate accounts |
| **Manage Alerts** | Create emergency alerts (strikes, road blocks) with severity levels (low/medium/high/critical) |
| **Contributions** | Unified pending review queue across all contribution types |
| **Suggestions** | Community suggestion inbox with review workflow |

### 6.4 Application Pages

| Page | URL | Description |
|------|-----|-------------|
| Landing | `/` | Introduction, navigation to map/agent/admin |
| User Map | `/pages/map.php` | Full-screen map with route finding + live tracking |
| Agent Login | `/pages/agent/login.php` | Agent login + registration |
| Agent Dashboard | `/pages/agent/dashboard.php` | Stats, leaderboard |
| Add Location | `/pages/agent/add-location.php` | Pin stops on map |
| Add Vehicle | `/pages/agent/add-vehicle.php` | Register vehicles |
| Add Route | `/pages/agent/add-route.php` | Build ordered routes |
| My Contributions | `/pages/agent/my-contributions.php` | Contribution history |
| Admin Login | `/pages/admin/login.php` | Admin authentication |
| Admin Dashboard | `/pages/admin/dashboard.php` | System overview |
| Manage Locations | `/pages/admin/manage-locations.php` | Bus stop management |
| Manage Vehicles | `/pages/admin/manage-vehicles.php` | Vehicle management |
| Manage Routes | `/pages/admin/manage-routes.php` | Route management |
| Manage Agents | `/pages/admin/manage-agents.php` | Agent management |
| Manage Alerts | `/pages/admin/manage-alerts.php` | Alert management |
| Contributions | `/pages/admin/contributions.php` | Review queue |
| Suggestions | `/pages/admin/suggestions.php` | Suggestion inbox |

**Design System:**
- CSS custom properties (design tokens) for theming
- Primary color: `#1A56DB` (blue), Accent: `#E8590C` (orange)
- Inter font family (Google Fonts CDN)
- Feather Icons (SVG icon set)
- Mobile-first responsive design with 3 breakpoints

---

## 7. Installation & Setup Guide

### 7.1 Prerequisites

| Requirement | Details |
|-------------|---------|
| **PHP** | 7.4 or higher |
| **MySQL** | 5.7+ or MariaDB 10.3+ |
| **Apache** | Via XAMPP, WAMP, MAMP, or standalone |
| **PHP Extensions** | `pdo`, `pdo_mysql`, `json`, `session`, `mbstring` |
| **Browser** | Chrome, Firefox, Safari, Edge (modern versions) |
| **Arduino IDE** | 2.x (for firmware flashing — optional) |

### 7.2 Web Application Setup

**Step 1: Install XAMPP**
```
Download from: https://www.apachefriends.org/
Install and start Apache + MySQL from the XAMPP Control Panel.
```

**Step 2: Clone the project**
```bash
cd C:/xampp/htdocs/
git clone https://github.com/your-username/sawari.git CCRC
```

**Step 3: Run the one-click installer**
```
Open browser → http://localhost/CCRC/setup.php
Click "Run Setup"

This will automatically:
  ✅ Check PHP version (7.4+) and required extensions
  ✅ Test MySQL connectivity
  ✅ Create the 'sawari' database
  ✅ Create all 9 tables with indexes and foreign keys
  ✅ Seed demo data (admin, agents, bus stops, routes, vehicles, alerts)
  ✅ Create required upload directories
```

**Step 4: Access the application**
```
Landing page:     http://localhost/CCRC/
User Map:         http://localhost/CCRC/pages/map.php
Agent Login:      http://localhost/CCRC/pages/agent/login.php
Admin Login:      http://localhost/CCRC/pages/admin/login.php
GPS Simulator:    http://localhost/CCRC/tools/gps-simulator.php
```

**Default Credentials (created by setup.php):**

| Role | Email | Password |
|------|-------|----------|
| Admin (superadmin) | `admin@sawari.com` | `admin123` |
| Agent | `ram@sawari.com` | `agent123` |
| Agent | `sita@sawari.com` | `agent123` |
| Agent | `bikash@sawari.com` | `agent123` |

> ⚠️ **Security:** Change default passwords immediately and **delete `setup.php`** in production.

### 7.3 Hardware Assembly

**Step 1: Test all components individually**
1. Upload a test sketch to ESP32 via USB (verify board responds)
2. Run I2C scanner sketch → confirm OLED at address `0x3C`
3. Connect GPS to USB-serial adapter → verify NMEA output at 9600 baud
4. Adjust buck converter potentiometer to exactly **5.0V** output (use multimeter)

**Step 2: Build power supply**
```
1. Solder 100µF electrolytic capacitor across buck converter INPUT
2. Solder 10µF ceramic capacitor across buck converter OUTPUT
3. Connect TVS diode: cathode (band) toward positive input
4. Install blade fuse holder in positive input line (2A fuse)
5. Add JST-XH 2-pin connector for vehicle power cable
```

**Step 3: Wire ESP32 to GPS module**
```
ESP32 3V3    ────── GPS VCC
ESP32 GND    ────── GPS GND
ESP32 GPIO16 ────── GPS TX    (cross-wired: ESP RX ← GPS TX)
ESP32 GPIO17 ────── GPS RX    (cross-wired: ESP TX → GPS RX)
```

**Step 4: Wire ESP32 to OLED display**
```
ESP32 3V3    ────── OLED VCC
ESP32 GND    ────── OLED GND
ESP32 GPIO21 ────── OLED SDA
ESP32 GPIO22 ────── OLED SCL
```

**Step 5: Wire 4 LEDs (each follows same pattern)**
```
ESP32 GPIOxx ──── 220Ω Resistor ──── LED Anode (+) ──── LED Cathode (−) ──── GND

  GPIO2  → Green LED  (Power indicator)
  GPIO4  → Blue LED   (WiFi status)
  GPIO13 → Yellow LED (GPS fix lock)
  GPIO14 → Red LED    (Data transmission blink)
```

**Step 6: Connect power system**
```
Buck Converter OUT+ → ESP32 VIN (5V)
Buck Converter OUT- → ESP32 GND
Connect ALL component GNDs to a common ground bus (star topology)
```

**Step 7: Enclosure assembly**
1. Mark and drill holes for: OLED window, 4 LED holes, cable gland, mounting screws
2. Install clear acrylic window over OLED cutout
3. Insert LED light pipes or diffusers into LED holes
4. Mount ESP32 and GPS module on standoffs inside enclosure
5. Route and secure wires with cable ties
6. Install IP65 cable gland for power cable entry
7. Seal enclosure, apply silicone around cable gland

### 7.4 Firmware Flashing

**Step 1: Install ESP32 board support in Arduino IDE**
```
File → Preferences → Additional Board Manager URLs:
  https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json

Tools → Board → Boards Manager → Search "ESP32" → Install "esp32 by Espressif Systems"
```

**Step 2: Install required libraries**
```
Sketch → Include Library → Manage Libraries:
  • TinyGPSPlus       by Mikal Hart        (GPS NMEA parsing)
  • U8g2              by Oliver Kraus       (OLED display graphics)
  • WiFiManager       by tzapu             (WiFi captive portal)
```

**Step 3: Configure firmware**

Edit `sawari_telemetry/config.h` before uploading:
```cpp
// Set your unique bus identifier (must match server-side vehicle record)
#define BUS_ID          1

// Set your server API endpoint
#define API_ENDPOINT    "http://your-server.com/CCRC/api/gps-device.php"
```

**Step 4: Board settings and upload**
```
Tools → Board:            "ESP32 Dev Module"
Tools → Upload Speed:     921600
Tools → Flash Frequency:  80MHz
Tools → Partition Scheme: "Default 4MB with spiffs"

Connect ESP32 via USB cable → Click Upload (→) button
```

### 7.5 First Boot & WiFi Configuration

1. **Power on** the ESP32 device (USB or 12V vehicle power)
2. OLED displays **SAWARI boot splash** with progress bar
3. Device creates WiFi access point: **"SAWARI_SETUP"**
4. On your phone/laptop, connect to **"SAWARI_SETUP"** network
5. A **captive portal** auto-opens in browser (or navigate to `192.168.4.1`)
6. Select your WiFi network from the scanned list
7. Enter WiFi password → Save
8. Device auto-connects, shows connected SSID on OLED, begins GPS tracking
9. **To reconfigure WiFi later:** Long-press the **BOOT button** for 2+ seconds

### 7.6 Testing Procedure

**Software Testing (no hardware required):**
```bash
# 1. Test database connectivity
Open: http://localhost/CCRC/setup.php   (should show all green checkmarks)

# 2. Test API endpoints
curl http://localhost/CCRC/api/locations.php?action=list
curl http://localhost/CCRC/api/vehicles.php?action=live

# 3. Test GPS simulator (simulates bus movement along a route)
Open: http://localhost/CCRC/tools/gps-simulator.php

# 4. Verify live tracking
Open: http://localhost/CCRC/pages/map.php  (bus markers should appear and move)
```

**Hardware Testing:**
1. Connect 12V bench power supply → verify buck converter outputs **5.0V ±0.1V**
2. **Power LED** (green) turns ON immediately
3. **OLED** shows boot splash → progress bar → WiFi status
4. Place device near a window/outdoors → **GPS LED** (yellow) turns ON after 30–90s
5. Once WiFi + GPS are both active → **Data LED** (red) blinks every 5 seconds
6. Verify data on server: check `http://localhost/CCRC/logs/gps-device.json`
7. Open Serial Monitor (115200 baud) to see detailed debug output

---

## 8. Power Consumption Analysis

### Battery Life Estimation

If powering from a portable battery instead of vehicle 12V:

| Battery Capacity | Average Draw | Estimated Runtime |
|-----------------|-------------|-------------------|
| 2000 mAh (small) | 175 mA | ~11.4 hours |
| 5000 mAh (medium) | 175 mA | ~28.5 hours |
| 10000 mAh (large) | 175 mA | ~57 hours |
| 20000 mAh (power bank) | 175 mA | ~114 hours (~4.75 days) |

> Note: Calculations assume typical current (175 mA average). Peak WiFi TX bursts (240 mA) are brief (~100–500ms).

### Current Consumption Breakdown

| Component | Sleep | Active | WiFi TX Peak |
|-----------|-------|--------|-------------|
| ESP32 | 10 µA | 80 mA | 240 mA |
| NEO-6M GPS | — | 35 mA | 50 mA (acquisition) |
| SH1106 OLED | — | 20 mA | 20 mA |
| 4× LEDs | — | 40 mA | 40 mA |
| **System Total** | **~10 µA** | **~175 mA** | **~350 mA** |

### Power Optimization Methods

| Method | Current Saved | Trade-off |
|--------|--------------|-----------|
| Reduce GPS to 0.5 Hz update | ~10 mA | Slightly less responsive position tracking |
| OLED off when stationary | ~20 mA | No visual status display when bus is parked |
| Reduce WiFi TX power | ~30 mA | Shorter effective WiFi range |
| Increase send interval (5s → 10s) | ~20 mA | Less frequent position updates |
| Deep sleep between sends | Major savings | 2–3 second wake-up latency per cycle |
| Turn off unused LEDs | ~30 mA | Reduced visual feedback for operators |

---

## 9. Storage Analysis

### Internal Memory Usage (ESP32)

| Memory Type | Total | Used by Firmware | Available |
|-------------|-------|-----------------|-----------|
| Flash (program) | 4 MB | ~200 KB | ~3.8 MB |
| SRAM (runtime) | 520 KB | ~8 KB | ~512 KB |
| LittleFS partition | ~1.5 MB | Variable | ~1.5 MB |

### Firmware Memory Breakdown

| Component | RAM Usage | Flash Usage |
|-----------|----------|-------------|
| TinyGPSPlus parser | ~600 bytes | ~10 KB |
| HardwareSerial buffer | 256 bytes | — |
| TelemetryData struct | 80 bytes | — |
| JSON format buffer | 400 bytes | — |
| LittleFS queue | ~500 bytes | 100 KB (max) |
| WiFiManager | ~2 KB | ~30 KB |
| U8g2 display buffer | ~1 KB | ~20 KB |
| **Total (approx)** | **~8 KB** | **~200 KB** |

### Offline Queue Storage

| Parameter | Value |
|-----------|-------|
| Queue file | `/queue.jsonl` on LittleFS |
| Record size | ~200 bytes per GPS entry |
| Max records | 500 (configurable via `MAX_QUEUE_SIZE` in config.h) |
| Max file size | ~100 KB |
| Overflow behavior | Oldest records discarded (FIFO) |

### Offline Duration Capacity

| Send Interval | Max Records | Offline Duration |
|--------------|------------|-----------------|
| 2 seconds | 500 | ~16.7 minutes |
| 5 seconds (default) | 500 | ~41.7 minutes |
| 10 seconds | 500 | ~83.3 minutes |

### Server Database Storage

| Table | Est. Row Size | 1K Rows | 100K Rows |
|-------|--------------|---------|-----------|
| locations | ~200 bytes | ~200 KB | ~20 MB |
| routes (with JSON) | ~500 bytes | ~500 KB | ~50 MB |
| vehicles | ~300 bytes | ~300 KB | ~30 MB |
| trips | ~200 bytes | ~200 KB | ~20 MB |
| GPS log (rolling) | ~300 bytes | 500 max | ~150 KB |

---

## 10. Troubleshooting Guide

### Web Application Issues

| Problem | Possible Cause | Solution |
|---------|---------------|----------|
| "Database connection failed" | MySQL not running or wrong credentials | Start MySQL in XAMPP; verify `api/config.php` settings |
| Blank map page | JavaScript error or Leaflet CDN unreachable | Check browser console (F12); verify internet connection |
| "No bus stops found near you" | No approved stops in the database | Run `setup.php` to seed data; approve via admin panel |
| "No approved routes available" | Routes exist but not yet approved | Admin → Manage Routes → Approve pending routes |
| Vehicle markers not appearing | No GPS data or stale (> 2 min old) | Use GPS simulator: `/tools/gps-simulator.php` |
| Images not uploading | Directory permission issue | Ensure `uploads/vehicles/` is writable (chmod 755) |
| Agent login fails | Account not approved, wrong password | Check agent status in admin panel; reset password |
| Search autocomplete empty | No approved locations | Seed data via setup.php or add via agent panel |
| Map tiles not loading | No internet / tile server issue | Check network; tiles require internet access |

### Hardware Issues

| Problem | Possible Cause | Solution |
|---------|---------------|----------|
| ESP32 won't power on | Fuse blown / reverse polarity | Check fuse; verify VIN polarity with multimeter |
| ESP32 keeps resetting | Insufficient power / brown-out | Use beefier power supply; verify 5V is stable |
| OLED display blank | Wrong I2C address or SDA/SCL swapped | Try 0x3C and 0x3D; verify pin connections |
| OLED shows garbled text | Wrong controller (SH1106 vs SSD1306) | Change display type in `display_handler.cpp` |
| GPS shows 0 satellites | Antenna blocked by metal/concrete | Move near window or outdoors |
| GPS fix takes > 5 minutes | Cold start, weak antenna | Wait up to 15 min for first-ever fix; check antenna |
| Low satellite count (1–3) | Poor sky visibility / urban canyon | Reposition for better sky view |
| High HDOP (> 5.0) | Poor satellite geometry | Wait 5–10 min for constellation to shift |
| WiFi won't connect | Wrong credentials or out of range | Long-press BOOT (2s) → Re-enter WiFi password |
| WiFi keeps disconnecting | Router too far / interference | Move closer; change router WiFi channel |
| Data LED never blinks | No GPS fix or server unreachable | Check GPS LED first; verify API_ENDPOINT in config.h |
| "SAWARI_SETUP" AP not visible | WiFi module issue | Hard-reset ESP32; erase flash and re-upload firmware |

### Serial Debug Output

Open Arduino IDE Serial Monitor at **115200 baud**:
```
========================================
  SAWARI Bus Telemetry Device v2.0.0
  ESP32 Dev Module
========================================
  Bus ID:  1
  API:     http://your-server.com/CCRC/api/gps-device.php
========================================

[INIT] Initializing LEDs...
[INIT] Initializing OLED display...
[INIT] Initializing LittleFS storage...
[INIT] Initializing GPS module...
[INIT] Initializing WiFi...
[INIT] WiFi connected — online mode active
[INIT] ======== INITIALIZATION COMPLETE ========

[MAIN] Sending telemetry to server...
[NETWORK] POST → http://your-server.com/CCRC/api/gps-device.php
[NETWORK] ✓ POST success (HTTP 200)
```

---

## 11. Future Improvements

### Planned Upgrades

| Priority | Feature | Description |
|----------|---------|-------------|
| 🔴 High | **Mobile App** | React Native iOS/Android app with push notifications |
| 🔴 High | **Self-hosted OSRM** | Nepal-specific road data for faster, private routing |
| 🔴 High | **GSM connectivity** | SIM800L module for cellular data (no WiFi dependency) |
| 🟡 Medium | **User Accounts** | Save favorite routes, trip history, preferences |
| 🟡 Medium | **Multi-language** | Nepali (देवनागरी) and English toggle |
| 🟡 Medium | **Payment Integration** | eSewa / Khalti fare payment |
| 🟡 Medium | **WebSocket Tracking** | Real-time push updates instead of 8s polling |
| 🟢 Low | **Voice Directions** | "Your stop is next!" audio alerts |
| 🟢 Low | **Accessibility** | Screen reader support, high contrast mode |
| 🟢 Low | **Predictive ETA** | ML model trained on historical trip data |

### Scalability Options

| Aspect | Current | Scaled |
|--------|---------|--------|
| Database | Single MySQL instance | Read replicas + Redis cache |
| Map tiles | OpenStreetMap CDN | Self-hosted tile server (OpenMapTiles) |
| OSRM routing | Public demo server | Self-hosted OSRM with Nepal PBF extract |
| GPS devices | WiFi-only (single bus) | GSM/SIM800L (city-wide fleet, hundreds of buses) |
| Backend | Single PHP server | Load-balanced PHP-FPM + Nginx |
| Real-time updates | AJAX polling (8s) | WebSockets (instant push updates) |
| Coverage | Kathmandu only | Multi-city: Pokhara, Bharatpur, Biratnagar, Butwal |

### Advanced Feature Ideas

- **Fleet analytics dashboard** — Vehicle utilization, route performance, peak hour analysis
- **Weather integration** — Rain delay warnings, seasonal route adjustments
- **NFC/QR ticketing** — Digital fare collection on buses
- **Inter-city routes** — Long-distance bus tracking (Kathmandu → Pokhara, etc.)
- **Accessibility data** — Wheelchair-accessible vehicle flags, station accessibility info
- **Crowd-sensing** — Estimate bus occupancy from GPS speed patterns
- **OTA firmware updates** — Push firmware updates to GPS devices over WiFi
- **Route optimization API** — Third-party integration for ride-sharing and logistics apps

---

## 12. Safety & Precautions

### ⚡ Electrical Safety

- **Always disconnect vehicle battery** before installing the GPS device
- Use proper gauge wire: 16 AWG for power, 22 AWG for signals
- **Fuse protection is mandatory** — never bypass the 2A fuse
- Keep all connections away from heat sources, moving parts, and water
- Do not install or modify the device while the vehicle engine is running
- Test with a bench power supply before connecting to vehicle electrical system

### 🔋 Battery Safety (if using portable power)

- Use quality LiPo/Li-ion cells with **built-in protection circuits** (BMS)
- Never short-circuit battery terminals
- Store batteries away from direct sunlight and extreme temperatures (-20°C to +60°C)
- Use appropriate charging modules (TP4056 with protection for single-cell)
- Dispose of batteries responsibly at authorized recycling facilities

### 📡 Data Safety

- **Change default admin/agent passwords** immediately after setup
- **Delete `setup.php`** after initial installation (contains seed credentials)
- Use **HTTPS** in production (Let's Encrypt provides free SSL certificates)
- GPS data is privacy-sensitive — implement appropriate data retention policies
- Comply with Nepal's **Electronic Transaction Act 2063** (2008)
- Inform bus operators/owners about GPS tracking installation (legal compliance)
- Perform regular database backups (automated daily recommended)
- Store password hashes only (bcrypt) — never store plaintext passwords

### 🚌 Vehicle Installation Safety

| ✅ DO | ❌ DON'T |
|-------|---------|
| Mount in a protected, ventilated area | Block the driver's field of view |
| Keep GPS antenna with clear sky view (dashboard/roof) | Install near airbag deployment zones |
| Secure all wiring with proper clips and cable ties | Route wires near brake/gas pedals or steering column |
| Use IP65 waterproof enclosure for external mounting | Mount on engine block or hot exhaust surfaces |
| Test thoroughly on bench before vehicle deployment | Use unprotected/unfused power connections |
| Label all wires and connections clearly | Leave loose wires dangling in the cabin |

---

## 13. License

This project is released under the **MIT License**.

```
MIT License

Copyright (c) 2026 SAWARI Team

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## 14. Credits

### Inspiration

- Nepal's informal public transit system that has **zero digital navigation** infrastructure
- Millions of daily commuters who struggle to find the right bus, overpay fares, and waste time
- The belief that **affordable technology** (a ~$30 GPS device + a free web app) can transform public transport accessibility
- The gap that Google Maps cannot fill — Nepal's bus routes are undocumented and informal

### Team

Built by **SAWARI Team** for the **CCRC Nexus IT Fest Hackathon 2026**.

### Tools & Technologies Used

| Category | Tools |
|----------|-------|
| **Languages** | PHP 7.4+, JavaScript (ES6+), C++ (Arduino), HTML5, CSS3, SQL |
| **Web Server** | Apache (via XAMPP) |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ |
| **Map Stack** | Leaflet.js 1.9.4, OpenStreetMap tiles, OSRM routing |
| **UI** | Feather Icons, Inter font (Google Fonts), Custom CSS design system |
| **Hardware** | ESP32 Dev Module, NEO-6M GPS, SH1106 OLED, buck converter, LEDs |
| **Firmware Libraries** | TinyGPSPlus, U8g2, WiFiManager, LittleFS |
| **Development** | VS Code, Arduino IDE 2.x, Git |

### Open-Source Acknowledgments

- [Leaflet.js](https://leafletjs.com/) — Interactive map library
- [OpenStreetMap](https://www.openstreetmap.org/) — Free map tile data
- [OSRM](http://project-osrm.org/) — Open Source Routing Machine
- [TinyGPSPlus](https://github.com/mikalhart/TinyGPSPlus) — GPS NMEA parsing library
- [U8g2](https://github.com/olikraus/u8g2) — Monochrome display graphics library
- [WiFiManager](https://github.com/tzapu/WiFiManager) — ESP32 WiFi configuration portal
- [Feather Icons](https://feathericons.com/) — Beautiful open-source SVG icons
- [Inter Font](https://rsms.me/inter/) — Professional typeface for UI

---

<p align="center">
  <strong>SAWARI — सवारी</strong><br>
  <em>Navigate Nepal's Public Transport with Confidence</em><br><br>
  Built with ❤️ in Kathmandu, Nepal<br>
  CCRC Nexus IT Fest Hackathon 2026
</p>
