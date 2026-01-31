<?php
/**
 * API สำหรับควบคุมการเริ่ม/หยุดการเติมน้ำมัน
 * เรียกใช้จาก Frontend
 * 
 * UPDATE: เพิ่มระบบ delay 1.5 วินาทีก่อนเริ่มนับลิตร
 */

// ตั้งค่า Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// ====== Database Configuration ======
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'fuel_db');

// ====== Functions ======

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed']));
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

function sendJSON($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ====== Main Logic ======

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['error' => 'Only POST method allowed'], 405);
}

$conn = getDBConnection();
$action = isset($_POST['action']) ? trim($_POST['action']) : null;

// ====== START - เริ่มการเติม ======
if ($action === 'start') {
    $target_liters = isset($_POST['target_liters']) ? (float)$_POST['target_liters'] : null;
    
    // Validate
    if (!$target_liters || $target_liters <= 0) {
        sendJSON(['error' => 'Invalid target_liters'], 400);
    }
    
    if ($target_liters > 10000) {
        sendJSON(['error' => 'Target too high (max 10000L)'], 400);
    }
    
    // บันทึกเวลาเริ่มต้นสำหรับ delay 1.5 วินาที
    $start_time = date('Y-m-d H:i:s');
    
    // เริ่มต้นข้อมูลใหม่ พร้อมบันทึก start_time
    $stmt = $conn->prepare(
        "INSERT INTO tb_gear_oil (flow_rate, total_liters, target_liters, status, start_time) 
         VALUES (0, 0, ?, 'filling', ?)"
    );
    
    $stmt->bind_param("ds", $target_liters, $start_time);
    
    if (!$stmt->execute()) {
        sendJSON(['error' => 'Failed to start filling'], 500);
    }
    
    $stmt->close();
    
    // เปิด Relay ทันที
    $conn->query("UPDATE tb_control SET control_status = 1 WHERE control_id = 1");
    
    sendJSON([
        'status' => 'started',
        'target' => round($target_liters, 3),
        'message' => 'เริ่มเติมน้ำมัน ' . $target_liters . ' ลิตร (รอ 1.5 วิก่อนนับลิตร)',
        'start_time' => $start_time,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// ====== STOP - หยุดการเติม ======
elseif ($action === 'stop') {
    // ปิด Relay
    $conn->query("UPDATE tb_control SET control_status = 0 WHERE control_id = 1");
    
    // อัปเดตสถานะเป็น idle
    $conn->query(
        "UPDATE tb_gear_oil 
         SET status = 'idle' 
         WHERE status = 'filling' 
         ORDER BY id DESC 
         LIMIT 1"
    );
    
    // ดึงข้อมูลปัจจุบัน
    $result = $conn->query(
        "SELECT total_liters, target_liters 
         FROM tb_gear_oil 
         ORDER BY id DESC 
         LIMIT 1"
    );
    
    $total = 0;
    $target = 0;
    
    if ($result && $row = $result->fetch_assoc()) {
        $total = (float)$row['total_liters'];
        $target = (float)$row['target_liters'];
    }
    
    sendJSON([
        'status' => 'stopped',
        'total_filled' => round($total, 3),
        'target' => round($target, 3),
        'message' => 'หยุดการเติม',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// ====== RESET - รีเซ็ตระบบ ======
elseif ($action === 'reset') {
    // ปิด Relay
    $conn->query("UPDATE tb_control SET control_status = 0 WHERE control_id = 1");
    
    // เพิ่มข้อมูลใหม่ที่เป็น idle
    $conn->query(
        "INSERT INTO tb_gear_oil (flow_rate, total_liters, target_liters, status, start_time) 
         VALUES (0, 0, NULL, 'idle', NULL)"
    );
    
    sendJSON([
        'status' => 'reset',
        'message' => 'รีเซ็ตระบบเรียบร้อย',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// ====== STATUS - ดึงสถานะปัจจุบัน ======
elseif ($action === 'status') {
    $result = $conn->query(
        "SELECT flow_rate, total_liters, target_liters, status, start_time, timestamp 
         FROM tb_gear_oil 
         ORDER BY id DESC 
         LIMIT 1"
    );
    
    if ($result && $row = $result->fetch_assoc()) {
        $data = [
            'flow_rate' => (float)$row['flow_rate'],
            'total_liters' => (float)$row['total_liters'],
            'target' => $row['target_liters'] ? (float)$row['target_liters'] : null,
            'status' => $row['status'],
            'start_time' => $row['start_time'],
            'timestamp' => $row['timestamp']
        ];
        
        // Progress
        if ($data['target'] && $data['target'] > 0) {
            $data['progress'] = round(($data['total_liters'] / $data['target']) * 100, 1);
        } else {
            $data['progress'] = 0;
        }
        
        // เช็คว่าอยู่ในช่วง delay หรือไม่
        if ($data['status'] === 'filling' && $data['start_time']) {
            $elapsed = time() - strtotime($data['start_time']);
            $data['delay_remaining'] = max(0, 1.5 - $elapsed);
        }
        
        sendJSON($data);
    } else {
        sendJSON([
            'status' => 'idle',
            'message' => 'No data available'
        ]);
    }
}

// ====== Invalid Action ======
else {
    sendJSON([
        'error' => 'Invalid action',
        'allowed_actions' => ['start', 'stop', 'reset', 'status']
    ], 400);
}

$conn->close();
?>