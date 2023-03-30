/*
 Navicat Premium Data Transfer

 Source Server Type    : MySQL
 Source Server Version : 80030
 Source Schema         : SimpleTracker

 Target Server Type    : MySQL
 Target Server Version : 80030
 File Encoding         : 65001

 Date: 06/03/2023 17:24:28
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for Peers_1
-- ----------------------------
DROP TABLE IF EXISTS `Peers_1`;
CREATE TABLE `Peers_1` (
  `info_hash` char(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `peer_id` binary(20) NOT NULL,
  `peer_id_char` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin GENERATED ALWAYS AS (convert(`peer_id` using latin1)) VIRTUAL,
  `user_agent` varchar(233) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_event` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_type` tinyint unsigned DEFAULT NULL,
  `ipv4` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ipv6` varchar(560) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `port` smallint unsigned DEFAULT NULL,
  PRIMARY KEY (`info_hash`,`peer_id`) USING BTREE,
  KEY `last_timestamp` (`last_timestamp` DESC) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for Peers_2
-- ----------------------------
DROP TABLE IF EXISTS `Peers_2`;
CREATE TABLE `Peers_2` (
  `info_hash` char(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `peer_id` binary(20) NOT NULL,
  `peer_id_char` char(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin GENERATED ALWAYS AS (convert(`peer_id` using latin1)) VIRTUAL,
  `user_agent` varchar(233) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_event` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_type` tinyint unsigned DEFAULT NULL,
  `ipv4` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ipv6` varchar(560) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `port` smallint unsigned DEFAULT NULL,
  PRIMARY KEY (`info_hash`,`peer_id`) USING BTREE,
  KEY `last_timestamp` (`last_timestamp` DESC) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for Torrents
-- ----------------------------
DROP TABLE IF EXISTS `Torrents`;
CREATE TABLE `Torrents` (
  `info_hash` char(40) COLLATE utf8mb4_general_ci NOT NULL,
  `total_completed` mediumint unsigned DEFAULT '0',
  PRIMARY KEY (`info_hash`),
  KEY `total_completed` (`total_completed` DESC) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
