-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 31, 2026 at 10:28 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fuel_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reset_system` ()   BEGIN
    -- ปิด Relay
    UPDATE tb_control SET control_status = 0 WHERE control_id = 1;
    
    -- เพิ่มข้อมูลใหม่ที่เป็น idle
    INSERT INTO tb_gear_oil (flow_rate, total_liters, target_liters, status, start_time)
    VALUES (0.000, 0.000, NULL, 'idle', NULL);
    
    -- ส่งผลลัพธ์
    SELECT 
        'reset' AS status,
        'System reset successfully' AS message,
        NOW() AS timestamp;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_start_filling` (IN `p_target_liters` DECIMAL(10,3))   BEGIN
    DECLARE v_start_time DATETIME;
    
    -- เช็ค input
    IF p_target_liters IS NULL OR p_target_liters <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Target liters must be greater than 0';
    END IF;
    
    IF p_target_liters > 10000 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Target liters too high (max 10000)';
    END IF;
    
    -- บันทึกเวลาเริ่มต้น
    SET v_start_time = NOW();
    
    -- เพิ่มข้อมูลการเติมใหม่
    INSERT INTO tb_gear_oil (flow_rate, total_liters, target_liters, status, start_time)
    VALUES (0.000, 0.000, p_target_liters, 'filling', v_start_time);
    
    -- เปิด Relay
    UPDATE tb_control SET control_status = 1 WHERE control_id = 1;
    
    -- ส่งผลลัพธ์
    SELECT 
        'started' AS status,
        p_target_liters AS target,
        v_start_time AS start_time,
        NOW() AS timestamp;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_stop_filling` ()   BEGIN
    DECLARE v_total DECIMAL(10,3);
    DECLARE v_target DECIMAL(10,3);
    
    -- ปิด Relay
    UPDATE tb_control SET control_status = 0 WHERE control_id = 1;
    
    -- อัปเดตสถานะเป็น idle
    UPDATE tb_gear_oil 
    SET status = 'idle'
    WHERE status = 'filling'
    ORDER BY id DESC
    LIMIT 1;
    
    -- ดึงข้อมูลปัจจุบัน
    SELECT total_liters, target_liters 
    INTO v_total, v_target
    FROM tb_gear_oil 
    ORDER BY id DESC 
    LIMIT 1;
    
    -- ส่งผลลัพธ์
    SELECT 
        'stopped' AS status,
        v_total AS total_filled,
        v_target AS target,
        NOW() AS timestamp;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tb_control`
--

CREATE TABLE `tb_control` (
  `control_id` int(11) NOT NULL,
  `control_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=ปิด, 1=เปิด',
  `control_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'เวลาอัปเดต'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ควบคุมสถานะ Relay (ปั๊มน้ำมัน)';

--
-- Dumping data for table `tb_control`
--

INSERT INTO `tb_control` (`control_id`, `control_status`, `control_updated`) VALUES
(1, 0, '2026-01-31 09:28:02');

-- --------------------------------------------------------

--
-- Table structure for table `tb_gear_oil`
--

CREATE TABLE `tb_gear_oil` (
  `id` int(11) NOT NULL,
  `flow_rate` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT 'อัตราการไหล (L/min)',
  `total_liters` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT 'ลิตรสะสม',
  `target_liters` decimal(10,3) DEFAULT NULL COMMENT 'เป้าหมาย (ลิตร)',
  `status` enum('idle','filling','completed') NOT NULL DEFAULT 'idle' COMMENT 'สถานะการเติม',
  `start_time` datetime DEFAULT NULL COMMENT 'เวลาเริ่มเติม (สำหรับ delay)',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'เวลาบันทึก'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='บันทึกข้อมูลการเติมน้ำมันเกียร์';

--
-- Dumping data for table `tb_gear_oil`
--

INSERT INTO `tb_gear_oil` (`id`, `flow_rate`, `total_liters`, `target_liters`, `status`, `start_time`, `timestamp`) VALUES
(1, 0.000, 0.000, NULL, 'idle', NULL, '2026-01-31 09:21:15'),
(2, 10.000, 10.000, 10.000, 'filling', '2026-01-31 10:22:59', '2026-01-31 09:22:59');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_latest_filling`
-- (See below for the actual view)
--
CREATE TABLE `vw_latest_filling` (
`id` int(11)
,`flow_rate` decimal(10,3)
,`total_liters` decimal(10,3)
,`target_liters` decimal(10,3)
,`status` enum('idle','filling','completed')
,`start_time` datetime
,`timestamp` timestamp
,`progress_percent` decimal(15,1)
,`elapsed_seconds` bigint(21)
,`is_delay_active` int(1)
,`delay_remaining` decimal(22,1)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_latest_filling`
--
DROP TABLE IF EXISTS `vw_latest_filling`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_latest_filling`  AS SELECT `tb_gear_oil`.`id` AS `id`, `tb_gear_oil`.`flow_rate` AS `flow_rate`, `tb_gear_oil`.`total_liters` AS `total_liters`, `tb_gear_oil`.`target_liters` AS `target_liters`, `tb_gear_oil`.`status` AS `status`, `tb_gear_oil`.`start_time` AS `start_time`, `tb_gear_oil`.`timestamp` AS `timestamp`, CASE WHEN `tb_gear_oil`.`target_liters` is not null AND `tb_gear_oil`.`target_liters` > 0 THEN round(`tb_gear_oil`.`total_liters` / `tb_gear_oil`.`target_liters` * 100,1) ELSE 0 END AS `progress_percent`, CASE WHEN `tb_gear_oil`.`start_time` is not null THEN timestampdiff(SECOND,`tb_gear_oil`.`start_time`,current_timestamp()) ELSE NULL END AS `elapsed_seconds`, CASE WHEN `tb_gear_oil`.`start_time` is not null AND `tb_gear_oil`.`status` = 'filling' THEN CASE WHEN timestampdiff(SECOND,`tb_gear_oil`.`start_time`,current_timestamp()) < 1.5 THEN 1 ELSE 0 END ELSE 0 END AS `is_delay_active`, CASE WHEN `tb_gear_oil`.`start_time` is not null AND `tb_gear_oil`.`status` = 'filling' THEN greatest(0,1.5 - timestampdiff(SECOND,`tb_gear_oil`.`start_time`,current_timestamp())) ELSE 0 END AS `delay_remaining` FROM `tb_gear_oil` ORDER BY `tb_gear_oil`.`id` DESC LIMIT 0, 1 ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tb_control`
--
ALTER TABLE `tb_control`
  ADD PRIMARY KEY (`control_id`);

--
-- Indexes for table `tb_gear_oil`
--
ALTER TABLE `tb_gear_oil`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_start_time` (`start_time`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tb_control`
--
ALTER TABLE `tb_control`
  MODIFY `control_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tb_gear_oil`
--
ALTER TABLE `tb_gear_oil`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
