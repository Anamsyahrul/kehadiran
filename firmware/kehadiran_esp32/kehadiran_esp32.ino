#include <Arduino.h>
#include <SPI.h>
#include <MFRC522.h>
#include <SD.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include "mbedtls/md.h"
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <RTClib.h>
#include <vector>
#include <utility>
#include <sys/time.h>
#include <cstring>
#include <cstdio>
#include <time.h>

#include "config.h"

#ifndef USE_SD_STORAGE
#define USE_SD_STORAGE 0
#endif

// RFID reader and peripherals
MFRC522 mfrc522(RFID_SS_PIN, RFID_RST_PIN);
RTC_DS3231 rtc;
Adafruit_SSD1306 display(OLED_WIDTH, OLED_HEIGHT, &Wire, -1);

bool rtcAvailable = false;
bool oledAvailable = false;

// Paths on SD
#define QUEUE_FILE "/events_queue.jsonl"
#define LOG_FILE "/attendance_log.csv"

// State
String lastUidHex = "";
unsigned long lastScanMs = 0;
unsigned long lastFlushMs = 0;
const unsigned long FLUSH_EVERY_MS = 5000;
unsigned long lastConfigFetchMs = 0;
const unsigned long CONFIG_FETCH_EVERY_MS = 30000;
bool gRegModeServer = false;
unsigned long lastDisplayMs = 0;
const unsigned long DISPLAY_REFRESH_MS = 1000;
const unsigned long SCAN_DISPLAY_DURATION_MS = 4000;
unsigned long showMessageUntil = 0;

struct Student { String uid; String name; String room; };
std::vector<Student> students;
String gStudentsVersion = "";
unsigned long lastStudentsSyncMs = 0;
const unsigned long STUDENT_SYNC_EVERY_MS = 5UL * 60UL * 1000UL;
#if !USE_SD_STORAGE
struct QueuedEvent {
  String uid;
  String ts;
};
std::vector<QueuedEvent> eventQueue;
const size_t MAX_RAM_QUEUE = 256;
#endif

// Forward declarations
void connectWiFi();
String generateNonce();
String hmacSha256Hex(const String &key, const String &message);
String eventsArrayFromQueue(int maxItems, String &leftoverOut, int &countOut);
bool removeProcessedFromQueue(const String &leftover);
bool postEventBatch(const String &eventsJson, int count);
String currentTimestampISO8601();
String uidToHex(const MFRC522::Uid &uid);
void beepSuccess();
void beepError();
void logToSd(const String &ts, const String &uidHex, const String &deviceId);
void enqueueEvent(const String &uidHex, const String &ts);
void flushQueueIfOnline();
void initI2CDevices();
void showIdle();
void showScan(const String &uidHex, const String &name, const String &status);
bool loadStudentsFromSd();
bool lookupNameByUid(const String &uidHex, String &outName);
bool registrationModeActive();
void updateServerConfigIfDue();
bool syncStudentsFromServer(bool force);
bool applyServerTimeFromIso(const String &iso);

static void debugLog(const String &msg) {
#if DEBUG
  Serial.println(msg);
#endif
}

static bool timeIsValid() {
  time_t now;
  time(&now);
  return now > 100000;
}

bool applyServerTimeFromIso(const String &iso) {
  if (iso.length() < 19) return false;
  int year, month, day, hour, minute, second;
  char tzSign = 'Z';
  int tzHour = 0, tzMin = 0;
  const char *c = iso.c_str();
  int parsed = sscanf(c, "%4d-%2d-%2dT%2d:%2d:%2d%c%2d:%2d",
                      &year, &month, &day, &hour, &minute, &second,
                      &tzSign, &tzHour, &tzMin);
  if (parsed < 6) {
    return false;
  }
  if (parsed < 9) {
    tzSign = 'Z';
    tzHour = 0;
    tzMin = 0;
  }

  DateTime dt(year, month, day, hour, minute, second);
  time_t epoch = dt.unixtime();
  if (tzSign == '+') {
    epoch -= (tzHour * 3600 + tzMin * 60);
  } else if (tzSign == '-') {
    epoch += (tzHour * 3600 + tzMin * 60);
  }

  if (epoch <= 0) return false;

  struct timeval tv;
  tv.tv_sec = epoch;
  tv.tv_usec = 0;
  settimeofday(&tv, nullptr);

  if (rtcAvailable) {
    struct tm localInfo;
    localtime_r(&epoch, &localInfo);
    rtc.adjust(DateTime(localInfo.tm_year + 1900,
                        localInfo.tm_mon + 1,
                        localInfo.tm_mday,
                        localInfo.tm_hour,
                        localInfo.tm_min,
                        localInfo.tm_sec));
  }

  debugLog("[TIME] Applied server time");
  return true;
}
void setup() {
#if DEBUG
  Serial.begin(115200);
  delay(200);
  Serial.println("[BOOT] Attendance firmware starting...");
  Serial.print("[CONF] Device ID: ");
  Serial.println(DEVICE_ID);
  Serial.print("[CONF] Secret length: ");
  Serial.println(strlen(DEVICE_SECRET));
#endif

  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, LOW);
  pinMode(LED_GREEN_PIN, OUTPUT);
  pinMode(LED_RED_PIN, OUTPUT);
  digitalWrite(LED_GREEN_PIN, LOW);
  digitalWrite(LED_RED_PIN, LOW);
  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, LOW);

  // SPI bus shared by RC522 + SD
  SPI.begin(SPI_SCK_PIN, SPI_MISO_PIN, SPI_MOSI_PIN);

  // SD card
#if USE_SD_STORAGE
  if (!SD.begin(SD_CS_PIN, SPI)) {
    debugLog("[SD] Failed to mount SD card!");
  } else {
    debugLog("[SD] SD mounted.");
    if (!SD.exists(LOG_FILE)) {
      File lf = SD.open(LOG_FILE, FILE_WRITE);
      if (lf) {
        lf.println("timestamp_iso8601,uid_hex,device_id");
        lf.close();
      }
    }
  }
#else
  debugLog("[SD] Disabled (server-only mode)");
#endif

  // RFID
  mfrc522.PCD_Init();
  delay(50);
  debugLog("[RFID] RC522 initialized.");

  // I2C devices (OLED + RTC)
  Wire.begin(I2C_SDA_PIN, I2C_SCL_PIN);
  initI2CDevices();

  // WiFi
  WiFi.mode(WIFI_STA);
  connectWiFi();

  // Timezone configurable via TIMEZONE_POSIX
  setenv("TZ", TIMEZONE_POSIX, 1);
  tzset();

  // Sync NTP if available
  if (WiFi.status() == WL_CONNECTED) {
    // Keep system clock in UTC; timezone applied via setenv("TZ", ...)
    configTime(0, 0, "pool.ntp.org", "time.google.com");
    debugLog("[TIME] Sync NTP...");
    time_t now = 0;
    int tries = 0;
    while (now < 100000 && tries < 20) {
      delay(1000);
      time(&now);
      tries++;
    }
    if (now >= 100000) {
      debugLog("[TIME] Time synced.");
      if (rtcAvailable) {
        struct tm info; localtime_r(&now, &info);
        rtc.adjust(DateTime(info.tm_year + 1900, info.tm_mon + 1, info.tm_mday, info.tm_hour, info.tm_min, info.tm_sec));
      }
    } else {
      debugLog("[TIME] NTP sync failed (continue).");
    }
  }

  // Startup indicator
  digitalWrite(LED_PIN, HIGH);
  delay(100);
  digitalWrite(LED_PIN, LOW);

  loadStudentsFromSd();
  showIdle();
  updateServerConfigIfDue();
  if (timeIsValid() && students.empty()) {
    if (syncStudentsFromServer(true)) {
      lastStudentsSyncMs = millis();
    }
  }
}

void loop() {
  if (millis() - lastFlushMs > FLUSH_EVERY_MS) {
    flushQueueIfOnline();
    lastFlushMs = millis();
  }
  updateServerConfigIfDue();

  if (WiFi.status() == WL_CONNECTED && timeIsValid() &&
      millis() - lastStudentsSyncMs > STUDENT_SYNC_EVERY_MS) {
    if (syncStudentsFromServer(false)) {
      lastStudentsSyncMs = millis();
    } else {
      lastStudentsSyncMs = millis() - (STUDENT_SYNC_EVERY_MS / 2); // retry sooner
    }
  }

  if (millis() - lastDisplayMs > DISPLAY_REFRESH_MS) {
    if (showMessageUntil == 0 || millis() >= showMessageUntil) {
      showIdle();
      lastDisplayMs = millis();
    }
  }

  if (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) {
    delay(10);
    return;
  }

  String uidHex = uidToHex(mfrc522.uid);
  unsigned long nowMs = millis();
  if (uidHex == lastUidHex && (nowMs - lastScanMs) < SCAN_DEBOUNCE_MS) {
    debugLog("[RFID] Debounced duplicate scan: " + uidHex);
    mfrc522.PICC_HaltA();
    mfrc522.PCD_StopCrypto1();
    return;
  }
  lastUidHex = uidHex;
  lastScanMs = nowMs;

  String ts = currentTimestampISO8601();
  debugLog("[RFID] UID=" + uidHex + " TS=" + ts);

  logToSd(ts, uidHex, DEVICE_ID);
  enqueueEvent(uidHex, ts);

  String name;
  bool hasName = lookupNameByUid(uidHex, name);
  bool regMode = registrationModeActive();
  if (hasName || regMode) {
    beepSuccess();
    showScan(uidHex, hasName ? name : String("(Daftar di server)"), (WiFi.status()==WL_CONNECTED?"Terekam":"Tersimpan offline"));
  } else {
    beepError();
    showScan(uidHex, String("(Tidak terdaftar)"), String("TOLAK"));
  }

  flushQueueIfOnline();

  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
}

void connectWiFi() {
  if (String(WIFI_SSID).length() == 0 || String(WIFI_SSID) == "YOUR_SSID") {
    debugLog("[WiFi] WIFI_SSID not set; offline mode.");
    return;
  }
  if (WiFi.status() == WL_CONNECTED) return;
  debugLog("[WiFi] Connecting to " + String(WIFI_SSID));
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && (millis() - start) < 15000) {
    delay(500);
    debugLog(".");
  }
  if (WiFi.status() == WL_CONNECTED) {
    debugLog("\n[WiFi] Connected. IP=" + WiFi.localIP().toString());
  } else {
    debugLog("\n[WiFi] Failed to connect.");
  }
}

String currentTimestampISO8601() {
  time_t now;
  if (rtcAvailable) {
    DateTime dt = rtc.now();
    struct tm tminfo = {0};
    tminfo.tm_year = dt.year() - 1900;
    tminfo.tm_mon  = dt.month() - 1;
    tminfo.tm_mday = dt.day();
    tminfo.tm_hour = dt.hour();
    tminfo.tm_min  = dt.minute();
    tminfo.tm_sec  = dt.second();
    now = mktime(&tminfo);
  } else {
    time(&now);
  }
  struct tm info;
  localtime_r(&now, &info);
  char buf[40];
  strftime(buf, sizeof(buf), "%Y-%m-%dT%H:%M:%S%z", &info);
  String s(buf);
  if (s.length() >= 5) {
    s = s.substring(0, s.length() - 2) + ":" + s.substring(s.length() - 2);
  }
  return s;
}

String uidToHex(const MFRC522::Uid &uid) {
  String out;
  for (byte i = 0; i < uid.size; i++) {
    if (uid.uidByte[i] < 0x10) out += "0";
    out += String(uid.uidByte[i], HEX);
  }
  out.toLowerCase();
  return out;
}

void beepSuccess() {
  digitalWrite(LED_PIN, HIGH);
  digitalWrite(LED_GREEN_PIN, HIGH);
  digitalWrite(BUZZER_PIN, HIGH);
  delay(80);
  digitalWrite(BUZZER_PIN, LOW);
  digitalWrite(LED_PIN, LOW);
  digitalWrite(LED_GREEN_PIN, LOW);
}

void beepError() {
  for (int i = 0; i < 2; i++) {
    digitalWrite(BUZZER_PIN, HIGH);
    delay(100);
    digitalWrite(BUZZER_PIN, LOW);
    delay(80);
  }
  digitalWrite(LED_RED_PIN, HIGH);
  delay(120);
  digitalWrite(LED_RED_PIN, LOW);
}

void logToSd(const String &ts, const String &uidHex, const String &deviceId) {
#if USE_SD_STORAGE
  if (!SD.begin(SD_CS_PIN, SPI)) return;
  File f = SD.open(LOG_FILE, FILE_WRITE);
  if (!f) {
    debugLog("[SD] Failed to open log file");
    return;
  }
  f.print(ts); f.print(","); f.print(uidHex); f.print(","); f.println(deviceId);
  f.close();
#else
  (void)ts;
  (void)uidHex;
  (void)deviceId;
#endif
}

void enqueueEvent(const String &uidHex, const String &ts) {
#if USE_SD_STORAGE
  if (!SD.begin(SD_CS_PIN, SPI)) return;
  File f = SD.open(QUEUE_FILE, FILE_WRITE);
  if (!f) {
    debugLog("[SD] Failed to open queue file");
    return;
  }
  StaticJsonDocument<128> doc;
  doc["uid"] = uidHex;
  doc["ts"] = ts;
  String line;
  serializeJson(doc, line);
  f.println(line);
  f.close();
#else
  if (eventQueue.size() >= MAX_RAM_QUEUE) {
    eventQueue.erase(eventQueue.begin());
  }
  eventQueue.push_back({uidHex, ts});
#endif
}

String generateNonce() {
  uint32_t a = esp_random();
  uint32_t b = esp_random();
  char buf[17];
  snprintf(buf, sizeof(buf), "%08x%08x", a, b);
  return String(buf);
}

String hmacSha256Hex(const String &key, const String &message) {
  const mbedtls_md_info_t *md_info = mbedtls_md_info_from_type(MBEDTLS_MD_SHA256);
  mbedtls_md_context_t ctx;
  mbedtls_md_init(&ctx);
  mbedtls_md_setup(&ctx, md_info, 1);
  mbedtls_md_hmac_starts(&ctx, (const unsigned char *)key.c_str(), key.length());
  mbedtls_md_hmac_update(&ctx, (const unsigned char *)message.c_str(), message.length());
  unsigned char hmac[32];
  mbedtls_md_hmac_finish(&ctx, hmac);
  mbedtls_md_free(&ctx);
  char out[65];
  for (int i = 0; i < 32; i++) sprintf(out + (i * 2), "%02x", hmac[i]);
  out[64] = 0;
  return String(out);
}

String eventsArrayFromQueue(int maxItems, String &leftoverOut, int &countOut) {
#if USE_SD_STORAGE
  countOut = 0;
  leftoverOut = "";
  if (!SD.begin(SD_CS_PIN, SPI)) return String("[]");
  if (!SD.exists(QUEUE_FILE)) return String("[]");

  File f = SD.open(QUEUE_FILE, FILE_READ);
  if (!f) return String("[]");

  String events = "[";
  bool first = true;
  while (f.available()) {
    String line = f.readStringUntil('\n');
    line.trim();
    if (line.length() == 0) continue;
    if (countOut < maxItems) {
      if (!first) events += ",";
      events += line;
      first = false;
      countOut++;
    } else {
      leftoverOut += line + "\n";
    }
  }
  f.close();
  events += "]";
  return events;
#else
  leftoverOut = "";
  countOut = 0;
  if (eventQueue.empty()) {
    return String("[]");
  }
  int toSend = static_cast<int>(eventQueue.size());
  if (toSend > maxItems) {
    toSend = maxItems;
  }
  String events = "[";
  for (int i = 0; i < toSend; i++) {
    if (i > 0) events += ",";
    events += "{\"uid\":\"";
    events += eventQueue[i].uid;
    events += "\",\"ts\":\"";
    events += eventQueue[i].ts;
    events += "\"}";
  }
  events += "]";
  leftoverOut = String(toSend); // encode number of dequeued entries for RAM queue
  countOut = toSend;
  return events;
#endif
}

bool removeProcessedFromQueue(const String &leftover) {
#if USE_SD_STORAGE
  if (!SD.begin(SD_CS_PIN, SPI)) return false;
  if (SD.exists(QUEUE_FILE)) {
    SD.remove(QUEUE_FILE);
  }
  File f = SD.open(QUEUE_FILE, FILE_WRITE);
  if (!f) return false;
  if (leftover.length() > 0) {
    f.print(leftover);
  }
  f.close();
  return true;
#else
  int toRemove = leftover.toInt();
  if (toRemove <= 0) return true;
  if (toRemove >= static_cast<int>(eventQueue.size())) {
    eventQueue.clear();
  } else {
    eventQueue.erase(eventQueue.begin(), eventQueue.begin() + toRemove);
  }
  return true;
#endif
}

bool postEventBatch(const String &eventsJson, int count) {
  if (count <= 0) return true;
  if (WiFi.status() != WL_CONNECTED) return false;

  String nonce = generateNonce();
  time_t now; time(&now);
  String tsHeader = String((uint32_t)now);

  String message = String(DEVICE_ID) + "|" + tsHeader + "|" + nonce + "|" + eventsJson;
  String hmac = hmacSha256Hex(DEVICE_SECRET, message);

  DynamicJsonDocument doc(2048 + (count * 64));
  doc["device_id"] = DEVICE_ID;
  doc["nonce"] = nonce;
  doc["ts"] = tsHeader;
  doc["hmac"] = hmac;
  doc["events"] = serialized(eventsJson);
  String body;
  serializeJson(doc, body);

  String url = String(API_BASE) + "/ingest.php";
  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  int code = http.POST(body);
  if (code <= 0) {
    debugLog("[HTTP] POST failed: " + String(code));
    http.end();
    return false;
  }

  String resp = http.getString();
  http.end();

  DynamicJsonDocument rdoc(512);
  DeserializationError err = deserializeJson(rdoc, resp);
  if (err) {
    debugLog("[HTTP] Invalid JSON response");
    return false;
  }
  bool ok = rdoc["ok"].is<bool>() ? rdoc["ok"].as<bool>() : false;
  int saved = rdoc["saved"].is<int>() ? rdoc["saved"].as<int>() : 0;
  if (ok && saved >= 0) {
    debugLog("[HTTP] Batch posted. saved=" + String(saved));
    return true;
  }
  debugLog("[HTTP] Server error: " + resp);
  return false;
}

void flushQueueIfOnline() {
  if (!timeIsValid()) return;
  if (WiFi.status() != WL_CONNECTED) return;
  String leftover;
  int count = 0;
  String eventsJson = eventsArrayFromQueue(BATCH_SIZE, leftover, count);
  if (count <= 0) return;

  if (postEventBatch(eventsJson, count)) {
    if (removeProcessedFromQueue(leftover)) {
      beepSuccess();
      showScan("", "Synced", "Sent to server");
    }
  } else {
    beepError();
    showScan("", "Sync Failed", "Will retry");
  }
}

bool registrationModeActive() {
#if USE_SD_STORAGE
  if (SD.begin(SD_CS_PIN, SPI)) {
    if (SD.exists(REG_MODE_FLAG_FILE)) return true;
  }
#endif
  return gRegModeServer;
}

void updateServerConfigIfDue() {
  if (WiFi.status() != WL_CONNECTED) return;
  if (millis() - lastConfigFetchMs < CONFIG_FETCH_EVERY_MS) return;
  lastConfigFetchMs = millis();
  time_t now; time(&now);
  String tsHeader = String((uint32_t)now);
  String nonce = generateNonce();
  String message = String(DEVICE_ID) + "|" + tsHeader + "|" + nonce;
  String hmac = hmacSha256Hex(DEVICE_SECRET, message);

  String url = String(API_BASE) + "/device_config.php?device_id=" + DEVICE_ID +
               "&ts=" + tsHeader + "&nonce=" + nonce + "&hmac=" + hmac;
  HTTPClient http;
  http.begin(url);
  int code = http.GET();
  if (code == 200) {
    String resp = http.getString();
    DynamicJsonDocument doc(256);
    if (deserializeJson(doc, resp) == DeserializationError::Ok) {
      if (doc["ok"].is<bool>() && doc["ok"].as<bool>()) {
        gRegModeServer = doc["reg_mode"].is<bool>() ? doc["reg_mode"].as<bool>() : false;
        const char *serverTimeStr = doc["server_time"].is<const char*>() ? doc["server_time"].as<const char*>() : nullptr;
        if (serverTimeStr && strlen(serverTimeStr) > 0) {
          applyServerTimeFromIso(String(serverTimeStr));
        }
        String cfgVersion = doc["students_version"].is<const char*>() ? String(doc["students_version"].as<const char*>()) : String("");
        if (cfgVersion.length() && cfgVersion != gStudentsVersion) {
          if (syncStudentsFromServer(true)) {
            lastStudentsSyncMs = millis();
          }
        }
      }
    }
  }
  http.end();
}

void initI2CDevices() {
  if (display.begin(SSD1306_SWITCHCAPVCC, OLED_ADDRESS)) {
    oledAvailable = true;
    display.clearDisplay();
    display.setTextSize(1);
    display.setTextColor(SSD1306_WHITE);
    display.setCursor(0, 0);
    display.println("Attendance v0.1");
    display.display();
  } else {
    debugLog("[OLED] Not found.");
  }

  if (rtc.begin()) {
    rtcAvailable = true;
    if (rtc.lostPower()) {
      debugLog("[RTC] Lost power, setting to compile time.");
      rtc.adjust(DateTime(F(__DATE__), F(__TIME__)));
    }
    DateTime dt = rtc.now();
    struct tm tminfo = {0};
    tminfo.tm_year = dt.year() - 1900;
    tminfo.tm_mon = dt.month() - 1;
    tminfo.tm_mday = dt.day();
    tminfo.tm_hour = dt.hour();
    tminfo.tm_min = dt.minute();
    tminfo.tm_sec = dt.second();
    time_t now = mktime(&tminfo);
    struct timeval tv = { .tv_sec = now, .tv_usec = 0 };
    settimeofday(&tv, nullptr);
  } else {
    debugLog("[RTC] Not found.");
  }
}

void showIdle() {
  if (!oledAvailable) return;
  showMessageUntil = 0;
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(SSD1306_WHITE);
  display.setCursor(0, 0);
  time_t now; time(&now); struct tm info; localtime_r(&now, &info);
  char tbuf[20]; strftime(tbuf, sizeof(tbuf), "%H:%M:%S", &info);
  display.print(tbuf);
  display.print("  ");
  display.println(DEVICE_ID);
  display.setCursor(0, 12);
  display.print("WiFi: "); display.println(WiFi.status()==WL_CONNECTED?"ON":"OFF");
  display.setCursor(0, 28);
  display.print("Scan kartu...");
  display.display();
}

void showScan(const String &uidHex, const String &name, const String &status) {
  if (!oledAvailable) return;
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(SSD1306_WHITE);
  display.setCursor(0, 0);
  display.println(status);
  display.setCursor(0, 14);
  display.print("UID: "); display.println(uidHex);
  display.setCursor(0, 28);
  display.print("Nama: "); display.println(name);
  display.display();
  showMessageUntil = millis() + SCAN_DISPLAY_DURATION_MS;
  lastDisplayMs = millis();
}

bool loadStudentsFromSd() {
  students.clear();
#if USE_SD_STORAGE
  if (!SD.begin(SD_CS_PIN, SPI)) return false;
  if (!SD.exists(STUDENTS_CSV)) return false;
  File f = SD.open(STUDENTS_CSV, FILE_READ);
  if (!f) return false;
  while (f.available()) {
    String line = f.readStringUntil('\n');
    line.trim();
    if (line.length() == 0) continue;
    int p1 = line.indexOf(',');
    int p2 = line.indexOf(',', p1+1);
    if (p1 <= 0) continue;
    String uid = line.substring(0, p1); uid.toLowerCase(); uid.replace(" ", "");
    String name = (p2>p1)? line.substring(p1+1, p2) : line.substring(p1+1);
    String room = (p2>p1)? line.substring(p2+1) : String("");
    students.push_back(Student{uid, name, room});
    if (students.size() >= 500) break;
  }
  f.close();
  debugLog("[SD] Loaded students: " + String(students.size()));
  return students.size() > 0;
#else
  return false;
#endif
}

bool lookupNameByUid(const String &uidHex, String &outName) {
  for (auto &s : students) {
    if (s.uid == uidHex) {
      outName = s.name;
      debugLog("[LOOKUP] UID dikenal: " + uidHex + " -> " + outName);
      return true;
    }
  }
  debugLog("[LOOKUP] UID tidak ditemukan di memori: " + uidHex);
  return false;
}

bool syncStudentsFromServer(bool force) {
  if (WiFi.status() != WL_CONNECTED) return false;
  if (!timeIsValid()) return false;
  time_t now; time(&now);
  String tsHeader = String((uint32_t)now);
  String nonce = generateNonce();
  String message = String(DEVICE_ID) + "|" + tsHeader + "|" + nonce;
  String hmac = hmacSha256Hex(DEVICE_SECRET, message);
  String url = String(API_BASE) + "/students_dump.php?device_id=" + DEVICE_ID +
               "&ts=" + tsHeader + "&nonce=" + nonce + "&hmac=" + hmac;

  HTTPClient http;
  http.begin(url);
  int code = http.GET();
  if (code != 200) {
    debugLog("[SYNC] students_dump HTTP code: " + String(code));
    String body = http.getString();
    if (body.length()) {
      debugLog("[SYNC] body: " + body);
    }
    http.end();
    return false;
  }
  String resp = http.getString();
  http.end();

  DynamicJsonDocument doc(32768);
  DeserializationError err = deserializeJson(doc, resp);
  if (err) {
    debugLog("[SYNC] JSON error: " + String(err.c_str()));
    return false;
  }
  if (!doc["ok"].is<bool>() || !doc["ok"].as<bool>()) {
    debugLog("[SYNC] Server returned not ok");
    return false;
  }
  String version = doc["version"].is<const char*>() ? String(doc["version"].as<const char*>()) : String("");
  JsonArray arr = doc["students"].is<JsonArray>() ? doc["students"].as<JsonArray>() : JsonArray();
  if (!force && version == gStudentsVersion && !students.empty()) {
    debugLog("[SYNC] Students unchanged");
    return true;
  }

  std::vector<Student> updated;
  for (JsonObject obj : arr) {
    String uid = String(obj["uid_hex"] | "");
    uid.toLowerCase(); uid.replace(" ", "");
    if (uid.length() == 0) continue;
    String name = String(obj["name"] | "");
    String room = String(obj["kelas"] | "");
    updated.push_back(Student{uid, name, room});
  }

#if USE_SD_STORAGE
  bool sdOk = SD.begin(SD_CS_PIN, SPI);
  if (sdOk) {
    if (SD.exists(STUDENTS_CSV)) SD.remove(STUDENTS_CSV);
    File f = SD.open(STUDENTS_CSV, FILE_WRITE);
    if (f) {
      for (auto &s : updated) {
        String line = s.uid + "," + s.name + "," + s.room + "\n";
        f.print(line);
      }
      f.close();
    } else {
      debugLog("[SYNC] Cannot write students.csv");
    }
  } else {
    debugLog("[SYNC] SD not available during sync");
  }
#endif

  students = std::move(updated);
  gStudentsVersion = version;
  debugLog("[SYNC] Students synced: " + String(students.size()));
  return true;
}
