/*
 * ระบบเติมน้ำมันเกียร์อัตโนมัติ - Arduino ESP32
 * พร้อม Flow Sensor และ Relay Control (ปรับปรุงการคำนวณ)
 */

#include <Arduino.h>
#include <WiFi.h>
#include <HTTPClient.h>

// ====== WiFi Configuration ======
const char* ssid = "Stupid";
const char* password = "Delomy2547";

// ====== Server URLs ======
const char* serverUrl = "http://154.215.14.103/fuel_system/api.php";
const char* relayUrl = "http://154.215.14.103/fuel_system/relay_status.php";

// ====== Hardware Pins ======
#define FLOW_PIN 34           // Flow Sensor Input
#define RELAY_PIN 5           // Relay Control Output

// ====== Flow Sensor Calibration ======
// สำหรับ YF-S201: 450 pulses/liter (7.5 pulses/sec @ 1 L/min)
// สำหรับเซ็นเซอร์อื่น ให้ปรับค่า PULSES_PER_LITER
#define PULSES_PER_LITER 450.0  // แก้: ใช้ค่าที่แม่นยำจากการ calibrate จริง
#define MIN_FLOW_THRESHOLD 0.01 // L/min - กรองสัญญาณรบกวน

// ====== Timing Settings ======
#define SAMPLE_PERIOD 1000        // สุ่มตัวอย่างทุก 1 วินาที (แม่นยำกว่า)
#define SEND_INTERVAL 1000        // ส่งข้อมูลทุก 1 วินาที
#define RELAY_CHECK_INTERVAL 300  // ตรวจสอบ relay ทุก 300ms
#define WIFI_RETRY_DELAY 5000     // รอ 5 วินาทีก่อน reconnect WiFi

// ====== Moving Average Filter ======
#define FILTER_SIZE 5
float flowRateBuffer[FILTER_SIZE] = {0};
int filterIndex = 0;

// ====== Global Variables ======
volatile uint32_t pulse_count = 0;
volatile unsigned long last_pulse_time = 0;
volatile unsigned long pulse_interval = 0;

unsigned long lastSendTime = 0;
unsigned long lastRelayCheckTime = 0;
unsigned long lastSampleTime = 0;
unsigned long lastWiFiCheck = 0;

int currentRelayStatus = 0;
bool wifiConnected = false;

float totalLiters = 0.0;  // ปริมาณน้ำมันสะสม

// ====== Interrupt Handler (ปรับปรุง) ======
void IRAM_ATTR flowPulseCounter() {
  unsigned long current_time = micros();
  pulse_interval = current_time - last_pulse_time;
  last_pulse_time = current_time;
  pulse_count++;
}

// ====== Moving Average Filter ======
float getFilteredFlowRate(float newValue) {
  flowRateBuffer[filterIndex] = newValue;
  filterIndex = (filterIndex + 1) % FILTER_SIZE;
  
  float sum = 0;
  int count = 0;
  for (int i = 0; i < FILTER_SIZE; i++) {
    if (flowRateBuffer[i] > 0) {
      sum += flowRateBuffer[i];
      count++;
    }
  }
  
  return (count > 0) ? (sum / count) : 0;
}

// ====== WiFi Connection Function ======
void connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) {
    wifiConnected = true;
    return;
  }

  Serial.println("\n[WiFi] กำลังเชื่อมต่อ WiFi...");
  WiFi.begin(ssid, password);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    wifiConnected = true;
    Serial.println("\n[WiFi] เชื่อมต่อสำเร็จ!");
    Serial.print("[WiFi] IP Address: ");
    Serial.println(WiFi.localIP());
  } else {
    wifiConnected = false;
    Serial.println("\n[WiFi] เชื่อมต่อล้มเหลว! จะลองใหม่ภายหลัง");
  }
}

// ====== Send Flow Rate to Server ======
bool sendFlowRate(float flow_rate, float sample_liters, float total_liters) {
  if (!wifiConnected) return false;

  HTTPClient http;
  http.begin(serverUrl);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  http.setTimeout(3000);

  String postData = "flow_rate=" + String(flow_rate, 3) + 
                    "&sample_liters=" + String(sample_liters, 4) +
                    "&total_liters=" + String(total_liters, 3);
  
  int httpCode = http.POST(postData);
  bool success = (httpCode > 0 && httpCode == 200);
  
  if (success) {
    String response = http.getString();
    Serial.println("[API] ส่งข้อมูล: " + postData);
    Serial.println("[API] ตอบกลับ: " + response);
  } else {
    Serial.println("[API] ข้อผิดพลาด HTTP Code: " + String(httpCode));
  }
  
  http.end();
  return success;
}

// ====== Check Relay Status from Server ======
void checkRelayStatus() {
  if (!wifiConnected) return;

  HTTPClient http;
  http.begin(relayUrl);
  http.setTimeout(2000);

  int httpCode = http.GET();
  
  if (httpCode > 0 && httpCode == 200) {
    String payload = http.getString();
    
    // Parse JSON response
    int newStatus = (payload.indexOf("\"relay\":1") >= 0) ? 1 : 0;
    
    if (newStatus != currentRelayStatus) {
      currentRelayStatus = newStatus;
      digitalWrite(RELAY_PIN, currentRelayStatus);
      
      Serial.print("[RELAY] สถานะเปลี่ยน: ");
      Serial.println(currentRelayStatus ? "เปิด (ON)" : "ปิด (OFF)");
      
      // Reset การนับเมื่อเปิด relay ใหม่
      if (currentRelayStatus == 1) {
        totalLiters = 0;
        Serial.println("[SYSTEM] รีเซ็ตปริมาณน้ำมันสะสม");
      }
    }
  } else {
    Serial.println("[RELAY] ไม่สามารถตรวจสอบสถานะได้");
  }
  
  http.end();
}

// ====== Setup Function ======
void setup() {
  Serial.begin(115200);
  delay(1000);
  
  Serial.println("\n\n========================================");
  Serial.println("  ระบบเติมน้ำมันเกียร์อัตโนมัติ");
  Serial.println("  Arduino ESP32 - Version 2.1");
  Serial.println("  ปรับปรุงการคำนวณ Flow Sensor");
  Serial.println("========================================\n");

  // Initialize Relay Pin
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, LOW);
  Serial.println("[INIT] Relay: OFF (Default)");

  // Initialize Flow Sensor Pin
  pinMode(FLOW_PIN, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(FLOW_PIN), flowPulseCounter, RISING);
  Serial.println("[INIT] Flow Sensor: Ready");
  Serial.print("[CONFIG] Calibration: ");
  Serial.print(PULSES_PER_LITER);
  Serial.println(" pulses/liter");

  // Connect to WiFi
  connectWiFi();
  
  lastSampleTime = millis();
  lastSendTime = millis();
  lastRelayCheckTime = millis();
  last_pulse_time = micros();
  
  Serial.println("\n[SYSTEM] พร้อมทำงาน!\n");
}

// ====== Main Loop ======
void loop() {
  unsigned long currentMillis = millis();

  // ตรวจสอบการเชื่อมต่อ WiFi
  if (WiFi.status() != WL_CONNECTED) {
    wifiConnected = false;
    if (currentMillis - lastWiFiCheck >= WIFI_RETRY_DELAY) {
      lastWiFiCheck = currentMillis;
      connectWiFi();
    }
  } else {
    wifiConnected = true;
  }

  // คำนวณ Flow Rate (ทุก SAMPLE_PERIOD ms)
  if (currentMillis - lastSampleTime >= SAMPLE_PERIOD) {
    // อ่านค่า pulse แบบ atomic operation
    noInterrupts();
    uint32_t pulses = pulse_count;
    pulse_count = 0;
    unsigned long interval = pulse_interval;
    interrupts();

    // คำนวณระยะเวลาที่แท้จริง (มีความแม่นยำสูง)
    float elapsed_seconds = (currentMillis - lastSampleTime) / 1000.0;
    
    // คำนวณปริมาณน้ำมันที่ไหลผ่านในช่วงนี้
    float sample_liters = (float)pulses / PULSES_PER_LITER;
    
    // คำนวณ Flow Rate (L/min)
    float raw_flow_rate = 0.0;
    if (elapsed_seconds > 0) {
      raw_flow_rate = (sample_liters / elapsed_seconds) * 60.0;
    }
    
    // กรองสัญญาณรบกวน
    if (raw_flow_rate < MIN_FLOW_THRESHOLD) {
      raw_flow_rate = 0.0;
      sample_liters = 0.0;
    }
    
    // ใช้ Moving Average Filter เพื่อความเรียบ
    float filtered_flow_rate = getFilteredFlowRate(raw_flow_rate);
    
    // สะสมปริมาณน้ำมันทั้งหมด
    if (sample_liters > 0) {
      totalLiters += sample_liters;
    }

    // แสดงผลบน Serial Monitor
    if (pulses > 0 || currentRelayStatus == 1) {
      Serial.println("----------------------------------------");
      Serial.print("[FLOW] Pulses: ");
      Serial.print(pulses);
      Serial.print(" | Interval: ");
      Serial.print(interval);
      Serial.println(" μs");
      
      Serial.print("[CALC] Raw Flow: ");
      Serial.print(raw_flow_rate, 3);
      Serial.print(" L/min | Filtered: ");
      Serial.print(filtered_flow_rate, 3);
      Serial.println(" L/min");
      
      Serial.print("[VOL] Sample: ");
      Serial.print(sample_liters, 4);
      Serial.print(" L | Total: ");
      Serial.print(totalLiters, 3);
      Serial.println(" L");
      Serial.println("----------------------------------------");
    }

    // ส่งข้อมูลไปยัง Server
    if (currentMillis - lastSendTime >= SEND_INTERVAL) {
      sendFlowRate(filtered_flow_rate, sample_liters, totalLiters);
      lastSendTime = currentMillis;
    }

    lastSampleTime = currentMillis;
  }

  // ตรวจสอบสถานะ Relay
  if (currentMillis - lastRelayCheckTime >= RELAY_CHECK_INTERVAL) {
    checkRelayStatus();
    lastRelayCheckTime = currentMillis;
  }

  // Small delay to prevent WDT reset
  delay(10);
}