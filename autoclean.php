<?php
if (PHP_SAPI !== 'cli') { die(); }
ini_set('memory_limit', '1024M');
require_once('config.php');
function LogStr(string $message, int $status = 0, bool $logToFile = true): bool {
	$logType = ($status === -1 ? '错误' : '信息');
	$date = date('Y-m-d');
	$time = date('H:i:s');
	$logStr = "[{$date} {$time}][{$logType}] {$message}.\n";
	echo $logStr;
	if ($logToFile) {
		if (!file_put_contents(LogDir . "/{$date}.log", $logStr, FILE_APPEND)) {
			LogStr('日志写入失败', -1, false);
			return false;
		}
	}
	return true;
}
function SendMessage(string $message) {
	if (empty($message)) {
		LogStr('发送消息失败, 因为消息为空', -1);
		return false;
	}
	return @file_get_contents(TG_API_URL . 'sendMessage?chat_id='  . TG_GROUP . '&text=' . rawurlencode($message));
}
function RestrictChatMember(int $userID, array $permissions = array('can_send_messages' => false, 'can_send_media_messages' => false, 'can_send_other_messages' => false, 'can_add_web_page_previews' => false), int $untilDate = -1) {
	if (empty($permissions)) {
		return false;
	}
	$permissionsJSON = json_encode($permissions);
	if ($untilDate === -1) {
		$untilDate = time() + 43200;
	}
	return @file_get_contents(TG_API_URL . "restrictChatMember?chat_id=" . TG_GROUP . "&user_id={$userID}&permissions={$permissionsJSON}&until_date={$untilDate}");
}
/*
function ipv6Filter(int $var) {
	return ($var === 1);
}
*/
function ConnectDB(): bool {
	global $db;
	if ($db === null) {
		$db = @new MySQLi(DBAddress, DBUser, DBPass, DBName, DBPort, DBSocket);
	}
	if ($db->connect_errno > 0) {
		CloseDB();
		LogStr('连接数据库时发生错误, 错误代码: ' . $db->connect_errno, -1);
		return false;
	}
	return true;
}
function CloseDB(): bool {
	global $db;
	if ($db !== null) {
		$db->close();
		$db = null;
	}
	return true;
}
function GetNginxPID(bool $getFromFile = true): int {
	$getMethod = ($getFromFile ? 'PID 文件' : '进程匹配');
	if ($getFromFile) {
		$nginxPID = (is_file(NginxPIDFile) ? file_get_contents(NginxPIDFile) : false);
	} else {
		$nginxPID = system('ps aux | grep nginx | grep master | awk \'{print $2}\'');
	}
	if ($nginxPID === false || !is_numeric($nginxPID)) {
		LogStr("获取 Nginx PID 失败 ({$getMethod})", -1);
		return -1;
	}
	$nginxPID = (int)$nginxPID;
	LogStr("获取 Nginx PID 成功 ({$getMethod}, PID: {$nginxPID})");
	return $nginxPID;
}
function CleanNginx(bool $manual = false) : bool {
	global $lastCleanTime;
	if (!$manual && ((time() - $lastCleanTime < 24) || is_file(NginxAccessLogFile))) {
		return false;
	}
	$lastCleanTime = time();
	$nginxPID = GetNginxPID();
	if ($nginxPID === -1) {
		$nginxPID = GetNginxPID(false);
		if ($nginxPID === -1) {
			return false;
		}
	}
	if (posix_kill($nginxPID, SIGUSR1)) {
		LogStr('重读 Nginx 日志成功');
		return true;
	}
	return false;
}
if (!is_dir(LogDir) && !mkdir(LogDir)) {
	LogStr('日志目录创建失败', -1, false);
	die();
}
LogStr('脚本开始运行..');
mysqli_report(MYSQLI_REPORT_OFF);
$db = null;
$lastHour1 = 0;
$lastHour2 = 0;
$lastDate1 = 0;
$lastDate2 = 0;
$lastCleanTime = 0;
//$curNginxTimestampDone = 0;
while (true) {
	$curMonth = intval(date('n'));
	$curDay = intval(date('j'));
	$curHour = intval(date('h'));
	//$curHour2 = intval(date('H'));
	$curMinute = intval(date('i'));
	/*
	if ($lastDate2 !== "{$curDay}-{$curHour2}" && $curHour2 === 8) {
		$restrictResult = RestrictChatMember(1473575912);
		if ($restrictResult !== false) {
			$lastDate2 = "{$curDay}-{$curHour2}";
		}
		LogStr("已尝试自动限制 Bot, 返回信息: {$restrictResult}", (($restrictResult !== false) ? 0 : -1));
	}
	*/
	$cleanRule1 = ($lastDate1 !== "{$curMonth}-{$curDay}" && $curDay === 1); // 完成数统计. (每月 1 号执行)
	$cleanRule2 = ($lastHour1 !== $curHour && $curMinute >= 50 && $curMinute <= 55); // 每小时于 45-50 分执行 1 次.
	$cleanRule3 = ($lastHour2 !== $curHour && $curMinute >= 55 && $curMinute <= 58); // 每小时于 50-58 分执行 1 次.
	if ($cleanRule1 || $cleanRule2 || $cleanRule3) {
		$curTime = time();
		$curYear = intval(date('Y'));
		if (!ConnectDB()) {
			sleep(DBRetryWaitTime);
			continue;
		}
		if ($cleanRule1) {
			if (!ConnectDB()) {
				sleep(DBRetryWaitTime);
				continue;
			}
			$lastDate1 = "{$curMonth}-{$curDay}"; // 成功连接数据库后允许计时.
			$queryTimeStart1 = microtime(true);
			$totalCompletedQuery = $db->query("SELECT SUM(total_completed) FROM Torrents LIMIT 1");
			$totalCompleted = ($totalCompletedQuery !== false && ($totalCompletedResult = $totalCompletedQuery->fetch_row()) !== false && $totalCompletedResult !== null) ? intval($totalCompletedResult[0]) : 0;
			$popularTorrentsQuery = $db->query('SELECT * FROM Torrents ORDER BY total_completed DESC LIMIT 50');
			if ($popularTorrentsQuery !== false) {
				$popularTorrentMessage = '';
				while ($popularTorrentResult = $popularTorrentsQuery->fetch_assoc()) {
					$popularTorrentMessage .= "种子 Hash: {$popularTorrentResult['info_hash']} (完成数: {$popularTorrentResult['total_completed']}).\n";
				}
				if ($curMonth === 1) {
					$curYear--;
					$curMonth = 12;
				} else {
					$curMonth = str_pad($curMonth - 1, 2, 0 ,STR_PAD_LEFT);
				}
				$startDate = "{$curYear}-{$curMonth}-01";
				$endDate = date('Y-m-d', strtotime('-1 day'));
				$popularTorrentMessage = "服务器 Tracker 已统计完成数记录 ({$startDate} 至 {$endDate}), 本次花费时间: " . round(microtime(true) - $queryTimeStart1, 3) . " 秒, 并将进行自动清理.\n本月共计完成数: {$totalCompleted}.\n\n{$popularTorrentMessage}";
				if (($sendMessageRetCode = SendMessage($popularTorrentMessage)) === false) {
					$lastDate1 = 0;
					LogStr('发送消息失败, 将不清理 MySQL Torrents 表 (返回值: ' . var_export($sendMessageRetCode, true) . ')', -1);
				} else {
					$cleanTimeStart1 = microtime(true);
					if ($db->query('TRUNCATE TABLE Torrents') !== false) {
						LogStr('清理 MySQL Torrents 表成功, 本次花费时间: ' . round(microtime(true) - $cleanTimeStart1, 3) . ' 秒');
					}
				}
			}
		}

		if ($cleanRule2) {
			# 生成首页统计缓存.
			if (!ConnectDB()) {
				sleep(DBRetryWaitTime);
				continue;
			}
			$lastHour1 = $curHour; // 成功连接数据库后允许计时.
			$queryTimeStart2_0 = microtime(true);
			$indexOutput = '';
			$totalSeeder = 0;
			$totalLeecher = 0;
			#$totalUnknown = 0;
			$totalIPv6 = 0;
			$userAgentUsageList = array();
			$torrentList = array(0 => array(), 1 => array(), 2 => array());
			$peerIDList = array(0 => array(), 1 => array(), 2 => array()); // 完整 Peer ID 去重, 用于统计实际用户数.
			$basicQuerySQL = 'SELECT info_hash, peer_id, user_agent, last_type, ipv6';
			$table1QuerySQL = "{$basicQuerySQL} FROM Peers_1";
			$table2QuerySQL = "{$basicQuerySQL} FROM Peers_2";
			$compareSQL = ' WHERE last_timestamp >= \'' . date('Y-m-d H:i:s', $curTime - 3600) . '\'';
			if ($curHour === 1 || $curHour === 3 || $curHour === 5 || $curHour === 7 || $curHour === 9 || $curHour === 11) {
				$table1QuerySQL .= $compareSQL;
			} else {
				$table2QuerySQL .= $compareSQL;
			}
			$peerResult = $db->query("({$table1QuerySQL}) UNION ALL ({$table2QuerySQL})", MYSQLI_USE_RESULT);
			$peerCount2_0 = 0;
			$queryTimeStart2_0_p = 0;
			while ($peerRow = $peerResult->fetch_assoc()) {
				if (empty($peerRow['last_type'])) {
					continue;
				}
				$peerCount2_0++;
				if ($peerCount2_0 >= IndexCount) {
					$peerCount2_0 = 0;
					$queryTimeStart2_0_p++;
					usleep(IndexSleepTime);
				}
				$peerRow['last_type'] = intval($peerRow['last_type']);
				if ($peerRow['last_type'] === 2) {
					$totalSeeder++;
				} else if ($peerRow['last_type'] === 1) {
					$totalLeecher++;
				} else {
					continue;
					#$totalUnknown++;
				}
				if (empty($peerRow['user_agent']) || strlen($peerRow['user_agent']) < 4 || stripos($peerRow['user_agent'], 'curl/') === 0 || stripos($peerRow['user_agent'], 'Mozilla/') === 0 || strlen($peerRow['user_agent']) > 50) {
					$peerRow['user_agent'] = substr($peerRow['peer_id'], 0, 8);
				} else {
					$uaExplode = explode(' ', $peerRow['user_agent']);
					foreach ($uaExplode as $ua) {
						if (preg_match('/((?!-)[A-Za-z0-9-]{1,63}(?<!-)\\.)+[A-Za-z]{2,6}/', $ua) === 1) {
							$peerRow['user_agent'] = substr($peerRow['peer_id'], 0, 8);
							break;
						}
					}
				}
				if (!isset($userAgentUsageList[$peerRow['user_agent']])) {
					$userAgentUsageList[$peerRow['user_agent']] = array(0 => 0, 1 => 0, 2 => 0, 'ipv6' => 0);
				}
				if (!isset($peerIDList[$peerRow['last_type']][$peerRow['peer_id']])) {
					$peerIDList[$peerRow['last_type']][$peerRow['peer_id']] = 0;
				}
				$isIPv6 = (!empty($peerRow['ipv6']));
				if (!isset($torrentList[$peerRow['last_type']][$peerRow['info_hash']]) || ($isIPv6 && $torrentList[$peerRow['last_type']][$peerRow['info_hash']] === 0)) {
					$torrentList[$peerRow['last_type']][$peerRow['info_hash']] = ($isIPv6 ? 1 : 0);
				}
				if ($isIPv6) {
					$totalIPv6++;
					$userAgentUsageList[$peerRow['user_agent']]['ipv6'] += 1;
					if ($peerIDList[$peerRow['last_type']][$peerRow['peer_id']] === 0) {
						$peerIDList[$peerRow['last_type']][$peerRow['peer_id']] = 1;
					}
				}
				$userAgentUsageList[$peerRow['user_agent']][$peerRow['last_type']] += 1;
			}
			if ($peerResult !== false) {
				$peerResult->free();
			}
			$queryTimeStart2_1 = microtime(true);
			$realUser = 0;
			$realSeeder = 0;
			$realLeecher = 0;
			#$realUnknown = 0;
			$realIPv6 = 0;
			$realUserDupeArr = array();
			$realTorrent = 0;
			$realTorrentIPv6 = 0;
			$realTorrentSeeder = 0;
			$realTorrentLeecher = 0;
			#$realTorrentUnknown = 0;
			$realTorrentDupeArr = array();
			/*
			foreach ($peerIDList[0] as $unknownPeerID => $unknownIPv6) {
				if (!isset($peerIDList[1][$unknownPeerID]) && !isset($peerIDList[2][$unknownPeerID])) {
					$realUser++;
					if ($unknownIPv6 === 1) {
						$realIPv6++;
					}
				} else if (!in_array($unknownPeerID, $realUserDupeArr)) {
					$realUserDupeArr[$unknownPeerID] = $unknownIPv6;
				}
				$realUnknown++;
			}
			*/
			$peerCount2_1 = 0;
			$queryTimeStart2_10_p = 0;
			foreach ($peerIDList[1] as $leecherPeerID => $leecherIPv6) {
				$peerCount2_1++;
				if ($peerCount2_1 >= IndexCount) {
					$peerCount2_1 = 0;
					$queryTimeStart2_10_p++;
					usleep(IndexSleepTime);
				}
				if (!isset($peerIDList[0][$leecherPeerID]) && !isset($peerIDList[2][$leecherPeerID])) {
					$realUser++;
					if ($leecherIPv6 === 1) {
						$realIPv6++;
					}
				} else if (!in_array($leecherPeerID, $realUserDupeArr)) {
					$realUserDupeArr[$leecherPeerID] = $leecherIPv6;
				}
				$realLeecher++;
			}
			$peerCount2_1 = 0;
			$queryTimeStart2_11_p = 0;
			foreach ($peerIDList[2] as $seederPeerID => $seederIPv6) {
				$peerCount2_1++;
				if ($peerCount2_1 >= IndexCount) {
					$peerCount2_1 = 0;
					$queryTimeStart2_11_p++;
					usleep(IndexSleepTime);
				}
				if (!isset($peerIDList[1][$seederPeerID]) && !isset($peerIDList[0][$seederPeerID])) {
					$realUser++;
					if ($seederIPv6 === 1) {
						$realIPv6++;
					}
				} else if (!in_array($seederPeerID, $realUserDupeArr)) {
					$realUserDupeArr[$seederPeerID] = $seederIPv6;
				}
				$realSeeder++;
			}
			unset($peerIDList);
			$realUser += count($realUserDupeArr);
			$realIPv6 += count(array_filter($realUserDupeArr, function ($v) { return ($v === 1); }));
			unset($realUserDupeArr);
			/*
			foreach ($torrentList[0] as $unknownTorrent => $unknownTorrentIPv6) {
				if (!isset($torrentList[1][$unknownTorrent]) && !isset($torrentList[2][$unknownTorrent])) {
					$realTorrent++;
					if ($unknownTorrentIPv6 === 1) {
						$realTorrentIPv6++;
					}
				} else if (!in_array($unknownTorrent, $realTorrentDupeArr)) {
					$realTorrentDupeArr[$unknownTorrent] = $unknownTorrentIPv6;
				}
				$realTorrentUnknown++;
			}
			*/
			$peerCount2_1 = 0;
			$queryTimeStart2_12_p = 0;
			foreach ($torrentList[1] as $leecherTorrent => $leecherTorrentIPv6) {
				$peerCount2_1++;
				if ($peerCount2_1 >= IndexCount) {
					$peerCount2_1 = 0;
					$queryTimeStart2_12_p++;
					usleep(IndexSleepTime);
				}
				if (!isset($torrentList[0][$leecherTorrent]) && !isset($torrentList[2][$leecherTorrent])) {
					$realTorrent++;
					if ($leecherTorrentIPv6 === 1) {
						$realTorrentIPv6++;
					}
				} else if (!in_array($leecherTorrent, $realTorrentDupeArr)) {
					$realTorrentDupeArr[$leecherTorrent] = $leecherTorrentIPv6;
				}
				$realTorrentLeecher++;
			}
			$peerCount2_1 = 0;
			$queryTimeStart2_13_p = 0;
			foreach ($torrentList[2] as $seederTorrent => $seederTorrentIPv6) {
				$peerCount2_1++;
				if ($peerCount2_1 >= IndexCount) {
					$peerCount2_1 = 0;
					$queryTimeStart2_13_p++;
					usleep(IndexSleepTime);
				}
				if (!isset($torrentList[1][$seederTorrent]) && !isset($torrentList[0][$seederTorrent])) {
					$realTorrent++;
					if ($seederTorrentIPv6 === 1) {
						$realTorrentIPv6++;
					}
				} else if (!in_array($seederTorrent, $realTorrentDupeArr)) {
					$realTorrentDupeArr[$seederTorrent] = $seederTorrentIPv6;
				}
				$realTorrentSeeder++;
			}
			unset($torrentList);
			$realTorrent += count($realTorrentDupeArr);
			$realTorrentIPv6 += count(array_filter($realTorrentDupeArr, function ($v) { return ($v === 1); }));
			unset($realTorrentDupeArr);
			$queryTimeStart2_2 = microtime(true);
			$indexOutput .= "Tracker 状态信息 (近 1 小时) [缓存生成于 " . date('Y-m-d H:i:s', $curTime) . "]\n";
			$indexOutput .= "种子数: {$realTorrent}. (IPv6 种子数: {$realTorrentIPv6}, 做种数: {$realTorrentSeeder}, 下载数: {$realTorrentLeecher})\n"; 
			$indexOutput .= "用户数: " . ($totalSeeder + $totalLeecher) . ". (IPv6 用户数: {$totalIPv6}, 做种数: {$totalSeeder}, 下载数: {$totalLeecher})\n";
			$indexOutput .= "实际用户人数: {$realUser}. (IPv6 实际用户人数: {$realIPv6}, 实际做种人数: {$realSeeder}, 实际下载人数: {$realLeecher})\n";
			array_multisort(array_map(function ($v) { unset($v['ipv6']); return array_sum($v); }, $userAgentUsageList), SORT_DESC, $userAgentUsageList);
			foreach ($userAgentUsageList as $userAgent => $numOfUser) {
				$totalUserCount = $numOfUser[2] + $numOfUser[1] + $numOfUser[0];
				if ($totalUserCount < 5) {
					// 降序排序结果可使用 break 直接跳出整个循环以提升性能.
					break;
				}
				$userAgent = htmlspecialchars($userAgent, ENT_NOQUOTES, 'UTF-8');
				$indexOutput .= "客户端 {$userAgent} 的用户数: {$totalUserCount}. (IPv6 用户数: {$numOfUser['ipv6']}, 做种数: {$numOfUser[2]}, 下载数: {$numOfUser[1]})\n";
			}
			unset($userAgentUsageList);
			$queryTimeStart2_3 = microtime(true);
			file_put_contents('indexCache-MySQL.txt', $indexOutput, LOCK_EX);
			@chmod('indexCache-MySQL.txt', 0777);
			$queryTimeEnd2 = microtime(true);
			$queryTimeStart2_0_ps = $queryTimeStart2_0_p * (IndexSleepTime / 1000000);
			$queryTimeStart2_10_ps = $queryTimeStart2_10_p * (IndexSleepTime / 1000000);
			$queryTimeStart2_11_ps = $queryTimeStart2_11_p * (IndexSleepTime / 1000000);
			$queryTimeStart2_12_ps = $queryTimeStart2_12_p * (IndexSleepTime / 1000000);
			$queryTimeStart2_13_ps = $queryTimeStart2_13_p * (IndexSleepTime / 1000000);
			$queryTimeStart2_1x_pt = $queryTimeStart2_10_p + $queryTimeStart2_11_p + $queryTimeStart2_12_p + $queryTimeStart2_13_p;
			$queryTimeStart2_1x_pst = $queryTimeStart2_10_ps + $queryTimeStart2_11_ps + $queryTimeStart2_12_ps + $queryTimeStart2_13_ps;
			LogStr("生成首页统计缓存成功, 本次花费时间: 总共/" . round($queryTimeEnd2 - $queryTimeStart2_0, 3) . " 秒, 实际/" . round($queryTimeEnd2 - $queryTimeStart2_0 - $queryTimeStart2_1x_pst, 3) . " 秒, 一阶段 (数据库获取/数组分配)/" . round($queryTimeStart2_1 - $queryTimeStart2_0, 3) . "秒 (暂停/{$queryTimeStart2_0_p} 次, {$queryTimeStart2_0_ps} 秒), 二阶段 (实际用户分析)/" . round($queryTimeStart2_2 - $queryTimeStart2_1, 3) . " 秒 (暂停/{$queryTimeStart2_10_p} + {$queryTimeStart2_11_p} + {$queryTimeStart2_12_p} + {$queryTimeStart2_13_p} = {$queryTimeStart2_1x_pt} 次, {$queryTimeStart2_10_ps} + {$queryTimeStart2_11_ps} + {$queryTimeStart2_12_ps} + {$queryTimeStart2_13_ps} = {$queryTimeStart2_1x_pst} 秒), 三阶段 (User-Agent 用户数求和)/" . round($queryTimeEnd2 - $queryTimeStart2_2, 3) . " 秒");
		}

		if ($cleanRule3) {
			if (!ConnectDB()) {
				sleep(DBRetryWaitTime);
				continue;
			}
			$lastHour2 = $curHour; // 成功连接数据库后允许计时.
			# 清理 Nginx 日志和数据库.
			//if (($curNginxTimestampDone + 3600) < microtime(true)) {
			$curNginxTimestampDone = microtime(true);
			if (is_file(NginxAccessLogFile . '.tmp')) {
				system('rm -f ' . NginxAccessLogFile . '.tmp ' . NginxAccessLogFile . '-*');
			}
			if (is_file(NginxAccessLogFile) && system('mv ' . NginxAccessLogFile . ' '. NginxAccessLogFile . '.tmp') !== false) {
				LogStr('成功移动 Nginx 日志至临时');
			}
			LogStr('清理 Nginx 日志成功, 本次花费时间: ' . round(microtime(true) - $curNginxTimestampDone, 3) . ' 秒');
			CleanNginx(true);
			//}
			//if ($curHour === 1 || $curHour === 3 || $curHour === 5 || $curHour === 7 || $curHour === 9 || $curHour === 11) {
			$cleanTimeStart2 = microtime(true);
			$oldDBName = 'Peers_' . (($curHour === 1 || $curHour === 3 || $curHour === 5 || $curHour === 7 || $curHour === 9 || $curHour === 11) ? '1' : '2'); // 和 Announce 的 curDBName 相反.
			if ($db->query("TRUNCATE TABLE {$oldDBName}") !== false) {
				LogStr("清理 MySQL {$oldDBName} 表成功, 本次花费时间: " . round(microtime(true) - $cleanTimeStart2, 3) . ' 秒');
			}
			//}
		}
		CloseDB();
	}
	CleanNginx();
	sleep(CheckInterval);
}
?>
