#pragma once

// =====================
// Firmware Configuration
// =====================

// Toggle penggunaan microSD untuk antrian offline & log (biarkan 0 untuk mode server-only)
#define USE_SD_STORAGE 0

// WiFi credentials
#define WIFI_SSID "AndroidAP_5726"
#define WIFI_PASS "123456789"

// Device identity (must match row in web/sql tables or admin UI)
#define DEVICE_ID     "esp32-01"
#define DEVICE_SECRET "anam123"

// API base (no trailing slash). Example: http://192.168.1.10/kehadiran/web/api
#define API_BASE "http://10.11.174.121/kehadiran/web/api"

// Enable verbose serial logging
#define DEBUG 1

// Waktu lokal (format POSIX TZ, contoh: "WIB-7", "WITA-8", "UTC0")
#define TIMEZONE_POSIX "WIB-7"

// Debounce identical UID scans within N milliseconds
#define SCAN_DEBOUNCE_MS 2000

// Max events per POST batch
#define BATCH_SIZE 20

// Pins (ESP32 default SPI + typical RC522 & SD module)
#define SPI_SCK_PIN  18
#define SPI_MISO_PIN 19
#define SPI_MOSI_PIN 23

// RC522
#define RFID_SS_PIN  5   // RC522 SDA/SS to GPIO5
#define RFID_RST_PIN 27  // RC522 RST to GPIO27

// microSD (SPI) - opsional jika suatu saat mode offline diaktifkan
#define SD_CS_PIN    4   // SD Card CS to GPIO4

// Indicators
#define LED_PIN        2   // Onboard LED (GPIO2)
#define LED_GREEN_PIN 25   // External green LED
#define LED_RED_PIN   26   // External red LED
#define BUZZER_PIN    15   // Passive buzzer to GPIO15

// I2C (OLED + RTC)
#define I2C_SDA_PIN 21
#define I2C_SCL_PIN 22

// OLED
#define OLED_WIDTH   128
#define OLED_HEIGHT   64
#define OLED_ADDRESS 0x3C

// Optional local mapping file on microSD
#define STUDENTS_CSV "/students.csv"

// Registration mode flag (create empty file on microSD to allow unknown cards)
#define REG_MODE_FLAG_FILE "/register.mode"

/*
Wiring Reference (Example: ESP32 DevKit v1)

RC522  -> ESP32
-------------------------
SDA/SS -> GPIO5 (RFID_SS_PIN)
SCK    -> GPIO18 (SPI_SCK_PIN)
MOSI   -> GPIO23 (SPI_MOSI_PIN)
MISO   -> GPIO19 (SPI_MISO_PIN)
RST    -> GPIO27 (RFID_RST_PIN)
3.3V   -> 3V3
GND    -> GND

microSD (SPI Module) -> ESP32
------------------------------
CS     -> GPIO4 (SD_CS_PIN)
SCK    -> GPIO18 (SPI_SCK_PIN)
MOSI   -> GPIO23 (SPI_MOSI_PIN)
MISO   -> GPIO19 (SPI_MISO_PIN)
VCC    -> 3V3/5V (module dependent)
GND    -> GND

Buzzer -> ESP32
------------------------------
+      -> GPIO15 (BUZZER_PIN) via resistor
-      -> GND
 
LEDs -> ESP32
------------------------------
RED    -> GPIO26 (LED_RED_PIN) via resistor
GREEN  -> GPIO25 (LED_GREEN_PIN) via resistor

OLED 0.96" I2C -> ESP32
------------------------------
SDA    -> GPIO21 (I2C_SDA_PIN)
SCL    -> GPIO22 (I2C_SCL_PIN)
VCC    -> 3V3
GND    -> GND

RTC DS3231 -> ESP32
------------------------------
SDA    -> GPIO21 (I2C_SDA_PIN)
SCL    -> GPIO22 (I2C_SCL_PIN)
VCC    -> 3V3
GND    -> GND
*/
