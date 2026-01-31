<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบเติมน้ำมันเกียร์อัตโนมัติ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            position: relative;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .connection-status {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            color: white;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: blink 2s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        .connected {
            background: #4CAF50;
        }

        .disconnected {
            background: #f44336;
        }

        .status-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 35px;
            border-radius: 15px;
            margin-bottom: 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .flow-indicator {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .display {
            font-size: 56px;
            font-weight: bold;
            margin: 15px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            line-height: 1.2;
        }

        .target {
            font-size: 20px;
            opacity: 0.9;
            margin: 10px 0;
        }

        .status-badge {
            display: inline-block;
            padding: 10px 25px;
            border-radius: 25px;
            font-size: 16px;
            margin-top: 12px;
            background: rgba(255,255,255,0.25);
            backdrop-filter: blur(10px);
            font-weight: 600;
        }

        .status-badge.filling {
            background: #4CAF50;
            animation: pulse 1.5s infinite;
        }

        .status-badge.completed {
            background: #2196F3;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.85; }
        }

        .progress-container {
            margin: 25px 0;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: #555;
            font-size: 14px;
            font-weight: 600;
        }

        .progress-bar {
            width: 100%;
            height: 35px;
            background: linear-gradient(to right, #e0e0e0, #f5f5f5);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 15px;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);
        }

        .control-section {
            margin: 30px 0;
        }

        .input-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            color: #555;
            font-size: 15px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .input-wrapper {
            position: relative;
        }

        input[type="number"] {
            width: 100%;
            padding: 16px 50px 16px 16px;
            font-size: 22px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            transition: all 0.3s;
            font-weight: 600;
        }

        input[type="number"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .input-unit {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 18px;
            font-weight: 600;
            pointer-events: none;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        button {
            flex: 1;
            padding: 18px 20px;
            font-size: 18px;
            font-weight: bold;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .btn-start {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
        }

        .btn-start:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }

        .btn-stop {
            background: linear-gradient(135deg, #f44336 0%, #da190b 100%);
        }

        .btn-stop:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(244, 67, 54, 0.4);
        }

        .btn-reset {
            background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
        }

        .btn-reset:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 152, 0, 0.4);
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        button:active:not(:disabled) {
            transform: translateY(0px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 25px 0;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            border: 2px solid #e9ecef;
        }

        .stat-label {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #495057;
        }

        .info-text {
            text-align: center;
            color: #666;
            margin-top: 20px;
            font-size: 13px;
            line-height: 1.6;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 10px;
            color: white;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .notification.success { background: #4CAF50; }
        .notification.error { background: #f44336; }
        .notification.info { background: #2196F3; }
        .notification.warning { background: #FF9800; }

        @media (max-width: 600px) {
            .container {
                padding: 25px 20px;
            }

            h1 {
                font-size: 24px;
            }

            .display {
                font-size: 42px;
            }

            .button-group {
                flex-direction: column;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .connection-status {
                position: static;
                margin-bottom: 20px;
                justify-content: center;
            }
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="connection-status" id="connectionStatus">
            <span class="status-dot"></span>
            <span>กำลังเชื่อมต่อ...</span>
        </div>

        <h1>🛢️ ระบบเติมน้ำมันเกียร์</h1>
        <p class="subtitle">ระบบควบคุมอัตโนมัติด้วย Arduino ESP32</p>

        <div class="status-card">
            <div class="flow-indicator">อัตราการไหล: <span id="flowRate">0.00</span> L/min</div>
            <div class="display"><span id="totalDisplay">0.000</span> <small style="font-size: 0.5em;">ลิตร</small></div>
            <div class="target">เป้าหมาย: <span id="targetDisplay">0.000</span> ลิตร</div>
            <div class="status-badge" id="statusBadge">⚪ พร้อมใช้งาน</div>
        </div>

        <div class="progress-container">
            <div class="progress-label">
                <span>ความคืบหน้า</span>
                <span id="progressText">0%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressBar" style="width: 0%">0%</div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">📊 เติมไปแล้ว</div>
                <div class="stat-value"><span id="filledStat">0.000</span> L</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">🎯 เหลืออีก</div>
                <div class="stat-value"><span id="remainingStat">0.000</span> L</div>
            </div>
        </div>

        <div class="control-section">
            <div class="input-group">
                <label for="targetLiters">🎯 ระบุจำนวนลิตรที่ต้องการเติม</label>
                <div class="input-wrapper">
                    <input 
                        type="number" 
                        id="targetLiters" 
                        placeholder="ตัวอย่าง: 5.5" 
                        step="0.1" 
                        min="0.1" 
                        max="10000"
                        value="1.0"
                    >
                    <span class="input-unit">ลิตร</span>
                </div>
            </div>

            <div class="button-group">
                <button class="btn-start" id="btnStart" onclick="startFilling()">
                    ▶️ เริ่มเติม
                </button>
                <button class="btn-stop" id="btnStop" onclick="stopFilling()" disabled>
                    ⏹️ หยุด
                </button>
            </div>
            
            <div class="button-group">
                <button class="btn-reset" id="btnReset" onclick="resetSystem()">
                    🔄 รีเซ็ต
                </button>
            </div>
        </div>

        <div class="info-text">
            <p>✓ ระบบจะหยุดอัตโนมัติเมื่อเติมครบตามจำนวนที่กำหนด</p>
            <p>✓ สามารถหยุดด้วยตัวเองได้ตลอดเวลา</p>
        </div>

        <div class="footer">
            <p>Powered by Arduino ESP32 & PHP | Version 2.0</p>
            <p id="lastUpdate">Last Update: -</p>
        </div>
    </div>

    <script>
        // ====== Configuration ======
        const API_URL = 'api.php';
        const CONTROL_URL = 'control.php';
        const UPDATE_INTERVAL = 500; // อัปเดตทุก 500ms

        // ====== Global Variables ======
        let isConnected = false;
        let updateInterval = null;
        let lastStatus = null;
        let notificationTimeout = null;

        // ====== Update Display ======
        function updateDisplay() {
            fetch(API_URL + '?t=' + Date.now())
                .then(response => {
                    if (!response.ok) throw new Error('Network error');
                    return response.json();
                })
                .then(data => {
                    // อัปเดตสถานะการเชื่อมต่อ
                    if (!isConnected) {
                        isConnected = true;
                        updateConnectionStatus(true);
                    }

                    // ดึงค่าจาก response
                    const flowRate = parseFloat(data.flow_rate) || 0;
                    const total = parseFloat(data.total_liters) || 0;
                    const target = parseFloat(data.target) || 0;
                    const status = data.filling_status || 'idle';
                    const progress = parseFloat(data.progress) || 0;

                    // อัปเดตการแสดงผล
                    document.getElementById('flowRate').textContent = flowRate.toFixed(2);
                    document.getElementById('totalDisplay').textContent = total.toFixed(3);
                    document.getElementById('targetDisplay').textContent = target.toFixed(3);
                    document.getElementById('filledStat').textContent = total.toFixed(3);
                    
                    const remaining = Math.max(0, target - total);
                    document.getElementById('remainingStat').textContent = remaining.toFixed(3);

                    // อัปเดต Progress Bar
                    const progressBar = document.getElementById('progressBar');
                    const progressText = document.getElementById('progressText');
                    progressBar.style.width = progress + '%';
                    progressBar.textContent = progress.toFixed(1) + '%';
                    progressText.textContent = progress.toFixed(1) + '%';

                    // อัปเดตสถานะและปุ่ม
                    updateStatusUI(status);

                    // แจ้งเตือนเมื่อเติมครบ
                    if (status === 'completed' && lastStatus !== 'completed') {
                        showNotification('✅ เติมน้ำมันครบแล้ว! (' + total.toFixed(3) + ' ลิตร)', 'success');
                        playCompletionSound();
                    }

                    lastStatus = status;

                    // อัปเดตเวลา
                    const now = new Date();
                    document.getElementById('lastUpdate').textContent = 
                        'Last Update: ' + now.toLocaleTimeString('th-TH');
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (isConnected) {
                        isConnected = false;
                        updateConnectionStatus(false);
                    }
                });
        }

        // ====== Update Status UI ======
        function updateStatusUI(status) {
            const statusBadge = document.getElementById('statusBadge');
            const btnStart = document.getElementById('btnStart');
            const btnStop = document.getElementById('btnStop');
            const btnReset = document.getElementById('btnReset');

            if (status === 'filling') {
                statusBadge.textContent = '🟢 กำลังเติม';
                statusBadge.className = 'status-badge filling';
                btnStart.disabled = true;
                btnStop.disabled = false;
                btnReset.disabled = true;
            } else if (status === 'completed') {
                statusBadge.textContent = '✅ เติมครบแล้ว';
                statusBadge.className = 'status-badge completed';
                btnStart.disabled = false;
                btnStop.disabled = true;
                btnReset.disabled = false;
            } else {
                statusBadge.textContent = '⚪ พร้อมใช้งาน';
                statusBadge.className = 'status-badge';
                btnStart.disabled = false;
                btnStop.disabled = true;
                btnReset.disabled = false;
            }
        }

        // ====== Update Connection Status ======
        function updateConnectionStatus(connected) {
            const statusEl = document.getElementById('connectionStatus');
            const dot = statusEl.querySelector('.status-dot');
            const text = statusEl.querySelector('span:last-child');

            if (connected) {
                statusEl.className = 'connection-status connected';
                text.textContent = 'เชื่อมต่อแล้ว';
            } else {
                statusEl.className = 'connection-status disconnected';
                text.textContent = 'ไม่ได้เชื่อมต่อ';
            }
        }

        // ====== Show Notification ======
        function showNotification(message, type = 'info') {
            // ลบ notification เก่า
            const oldNotif = document.querySelector('.notification');
            if (oldNotif) oldNotif.remove();

            // สร้าง notification ใหม่
            const notif = document.createElement('div');
            notif.className = 'notification ' + type;
            notif.textContent = message;
            document.body.appendChild(notif);

            // ลบหลัง 4 วินาที
            if (notificationTimeout) clearTimeout(notificationTimeout);
            notificationTimeout = setTimeout(() => {
                notif.remove();
            }, 4000);
        }

        // ====== Play Completion Sound ======
        function playCompletionSound() {
            // สร้างเสียงแจ้งเตือนด้วย Web Audio API
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.frequency.value = 800;
            oscillator.type = 'sine';

            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
        }

        // ====== Start Filling ======
        function startFilling() {
            const target = parseFloat(document.getElementById('targetLiters').value);

            if (!target || target <= 0) {
                showNotification('⚠️ กรุณากรอกจำนวนลิตรที่ถูกต้อง', 'warning');
                return;
            }

            if (target > 10000) {
                showNotification('⚠️ จำนวนลิตรสูงเกินไป (สูงสุด 10,000 ลิตร)', 'error');
                return;
            }

            if (!confirm(`ต้องการเริ่มเติมน้ำมัน ${target} ลิตร หรือไม่?`)) {
                return;
            }

            fetch(CONTROL_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=start&target_liters=${target}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'started') {
                    showNotification(`🚀 เริ่มเติมน้ำมัน ${target} ลิตร`, 'success');
                    updateDisplay();
                } else {
                    showNotification('❌ ไม่สามารถเริ่มเติมได้', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
            });
        }

        // ====== Stop Filling ======
        function stopFilling() {
            if (!confirm('ต้องการหยุดการเติมน้ำมันหรือไม่?')) {
                return;
            }

            fetch(CONTROL_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=stop'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'stopped') {
                    showNotification('⏹️ หยุดการเติมแล้ว', 'info');
                    updateDisplay();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ เกิดข้อผิดพลาด', 'error');
            });
        }

        // ====== Reset System ======
        function resetSystem() {
            if (!confirm('ต้องการรีเซ็ตระบบหรือไม่?')) {
                return;
            }

            fetch(CONTROL_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=reset'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'reset') {
                    showNotification('🔄 รีเซ็ตระบบเรียบร้อย', 'info');
                    updateDisplay();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('❌ เกิดข้อผิดพลาด', 'error');
            });
        }

        // ====== Initialize ======
        function init() {
            console.log('🚀 เริ่มต้นระบบ...');
            updateDisplay();
            updateInterval = setInterval(updateDisplay, UPDATE_INTERVAL);
        }

        // ====== Start System ======
        init();

        // ====== Cleanup on Page Unload ======
        window.addEventListener('beforeunload', () => {
            if (updateInterval) {
                clearInterval(updateInterval);
            }
        });
    </script>
</body>
</html>