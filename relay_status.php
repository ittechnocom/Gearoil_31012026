<?php
/**
 * API สำหรับควบคุมและตรวจสอบสถานะ Relay
 * สำหรับ Arduino เช็คสถานะ Relay
 */

// ตั้งค่า Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

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
    
    return $conn;
}

function sendJSON($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ====== Main Logic ======

$conn = getDBConnection();

// ====== GET - ดึงสถานะ Relay ======
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query(
        "SELECT control_status, control_updated 
         FROM tb_control 
         WHERE control_id = 1 
         LIMIT 1"
    );
    
    if ($result && $row = $result->fetch_assoc()) {
        sendJSON([
            'relay' => (int)$row['control_status'],
            'updated' => $row['control_updated']
        ]);
    } else {
        sendJSON(['relay' => 0, 'updated' => null]);
    }
}

// ====== POST - อัปเดตสถานะ Relay ======
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = isset($_POST['status']) ? (int)$_POST['status'] : null;
    
    if ($new_status === null || ($new_status !== 0 && $new_status !== 1)) {
        sendJSON(['error' => 'Invalid status value'], 400);
    }
    
    $stmt = $conn->prepare(
        "UPDATE tb_control 
         SET control_status = ?, control_updated = NOW() 
         WHERE control_id = 1"
    );
    
    $stmt->bind_param("i", $new_status);
    
    if ($stmt->execute()) {
        sendJSON([
            'status' => 'ok',
            'relay' => $new_status,
            'message' => $new_status ? 'Relay ON' : 'Relay OFF'
        ]);
    } else {
        sendJSON(['error' => 'Update failed'], 500);
    }
    
    $stmt->close();
}

// ====== Method Not Allowed ======
else {
    sendJSON(['error' => 'Method not allowed'], 405);
}

$conn->close();
?>