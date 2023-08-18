/*
 Navicat Premium Data Transfer

 Source Server         : tracker
 Source Server Type    : MariaDB
 Source Server Version : 101104
 Source Schema         : SimpleTracker

 Target Server Type    : MariaDB
 Target Server Version : 101104
 File Encoding         : 65001

 Date: 18/08/2023 01:46:02
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for Messages
-- ----------------------------
DROP TABLE IF EXISTS `Messages`;
CREATE TABLE `Messages` (
  `info_hash` char(40) NOT NULL,
  `key` varchar(24) NOT NULL,
  `message` varchar(24) DEFAULT NULL,
  `last_timestamp` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`info_hash`,`key`),
  KEY `last_timestamp` (`last_timestamp` DESC) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Table structure for Peers_1
-- ----------------------------
DROP TABLE IF EXISTS `Peers_1`;
CREATE TABLE `Peers_1` (
  `info_hash` char(40) NOT NULL,
  `peer_id` binary(20) NOT NULL,
  `peer_id_char` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin GENERATED ALWAYS AS (convert(`peer_id` using latin1)) VIRTUAL,
  `user_agent` varchar(233) DEFAULT NULL,
  `last_timestamp` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_event` varchar(9) DEFAULT NULL,
  `last_type` tinyint(1) unsigned DEFAULT NULL,
  `ipv4` varchar(80) DEFAULT NULL,
  `ipv6` varchar(560) DEFAULT NULL,
  `port` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`info_hash`,`peer_id`) USING BTREE,
  KEY `last_timestamp` (`last_timestamp` DESC) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for Peers_2
-- ----------------------------
DROP TABLE IF EXISTS `Peers_2`;
CREATE TABLE `Peers_2` (
  `info_hash` char(40) NOT NULL,
  `peer_id` binary(20) NOT NULL,
  `peer_id_char` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin GENERATED ALWAYS AS (convert(`peer_id` using latin1)) VIRTUAL,
  `user_agent` varchar(233) DEFAULT NULL,
  `last_timestamp` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_event` varchar(9) DEFAULT NULL,
  `last_type` tinyint(1) unsigned DEFAULT NULL,
  `ipv4` varchar(80) DEFAULT NULL,
  `ipv6` varchar(560) DEFAULT NULL,
  `port` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`info_hash`,`peer_id`) USING BTREE,
  KEY `last_timestamp` (`last_timestamp` DESC) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for SimpleTrackerKey
-- ----------------------------
DROP TABLE IF EXISTS `SimpleTrackerKey`;
CREATE TABLE `SimpleTrackerKey` (
  `key` varchar(24) NOT NULL,
  `expiry_date` date DEFAULT (curdate() + interval 3 month),
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------
-- Table structure for Torrents
-- ----------------------------
DROP TABLE IF EXISTS `Torrents`;
CREATE TABLE `Torrents` (
  `info_hash` char(40) NOT NULL,
  `total_completed` mediumint(8) unsigned DEFAULT 0,
  PRIMARY KEY (`info_hash`),
  KEY `total_completed` (`total_completed` DESC) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `Blocklist`;
CREATE TABLE `Blocklist` (
  `info_hash` char(40) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`info_hash`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
