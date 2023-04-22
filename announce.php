<?php
/*
GET 请求体
array (
  'info_hash' => 'E{Ÿ}•Ïy‚¶Þ¶ÅýÑO?€',
  'peer_id' => '-qB4431-IXk!wBcCHGDb',
  'port' => '16384',
  'uploaded' => '0',
  'downloaded' => '0',
  'left' => '3321664',
  'corrupt' => '0',
  'key' => '4545B0A5',
  'event' => 'started',
  'numwant' => '200',
  'compact' => '1',
  'no_peer_id' => '1',
  'supportcrypto' => '1',
  'redundant' => '0',
  'ipv4' => '127.0.0.1',
  'ipv6' => '::1',
)
失败结构
$t = array(
	'failure reason' => 'Test'
);
成功结构
$t = array(
	'warning message' => '警告: 正在使用测试中的 Tracker...',
	'interval' => 600,
	'min interval' => 300,
	'complete' => 114,
	'incomplete' => 514,
	'peers' => array(array('peer_id' => 'a', 'ip' => '127.0.0.1', 'port' => 2333), array('peer_id' => 'b', 'ip' => '127.0.0.1', 'port' => 2334)),
	#'peers' => array('\x1\x0\x0\x7f\1d\09', '\x1\x0\x0\x7f\1e\09') # Compact mode
);
文件结构
$t = array(
	'announce' => 'http://announce.url',
	'comment' => 'This is comments',
	'created by' => 'mktorrent 1.0',
	'creation date' => 1585361538,
	'info' => array(
		'files' => array(
			array(
				'length' => 5,
				'path' => array('README.md')
			),
			array(
				'length' => 0,
				'path' => array('README1.md')
			)
		),
		'name' => '.',
		'piece length' => 262144,
		'pieces' => 'rhrxxxxxxxxrrrrrr',
		'private' => 1
	)
);
*/
function AddIPToArr(&$arr, ...$ipArr) {
	foreach ($ipArr as $value) {
		if ($value === null) {
			continue;
		}
		if (is_array($value)) {
			foreach ($value as $value2) {
				$value2 = strtolower($value2);
				if ((isset($arr['ipv4']) && in_array($value2, $arr['ipv4'])) || (isset($arr['ipv6']) && in_array($value2, $arr['ipv6']))) {
					continue;
				}
				if (filter_var($value2, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
					$arr['ipv4'][] = $value2;
				} else if (filter_var($value2, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
					$arr['ipv6'][] = $value2;
				}
			}
		} else if (is_string($value)) {
			$value = strtolower($value);
			if ((isset($arr['ipv4']) && in_array($value, $arr['ipv4'])) || (isset($arr['ipv6']) && in_array($value, $arr['ipv6']))) {
				continue;
			}
			if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				$arr['ipv4'][] = $value;
			} else if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				$arr['ipv6'][] = $value;
			}
		}
	}
}
// 返回 Debug Level.
function CheckKeyAvailability(string $key): int {
	if ($key === GeneralDebugKey) {
		return 1;
	}
	if ($key === AdminKey) {
		return 10;
	}
	global $db;
	if (strpos($key, UserKeyPrefix) === 0) {
		$clientEscapedDebugKey = $db->escape_string($key);
		if (($keyAvailabilityCheckQuery = $db->query("SELECT 1 FROM SimpleTrackerKey WHERE `key` = '{$clientEscapedDebugKey}' AND (expiry_date > CURDATE() OR expiry_date IS NULL) LIMIT 1")) !== false && $keyAvailabilityCheckQuery->num_rows > 0) {
			return 10;
		}
	}
	return 0;
}
require_once('config.php');
require_once('include.bencode.php');
header('Content-Type: text/plain; charset=utf-8');
$clientUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
$clientInfoHash = $_GET['info_hash'] ?? null;
$clientPeerID = $_GET['peer_id'] ?? null;
$clientEvent = $_GET['event'] ?? null;
$clientSupportCrypto = (isset($_GET['supportcrypto']) && intval($_GET['supportcrypto']) === 1) ? true : false;
if ($clientInfoHash === null || $clientPeerID === null || (!empty($clientEvent) && !in_array(strtolower($clientEvent), array('started', 'stopped', 'paused', 'completed', 'update'))) || ($clientUserAgent !== null && strlen($clientUserAgent) > 233)) {
	die(GenerateBencode(array('failure reason' => ErrorMessage[2])));
} else if ($clientUserAgent !== null && (empty($clientUserAgent) || stripos($clientUserAgent, 'Amazon CloudFront') !== false)) {
	$clientUserAgent = null;
}
$clientEvent = strtolower($clientEvent);
if (preg_match('/^-(XL|SD|XF|QD|BN|DL)(\d+)-/i', $clientPeerID) === 1 || ($clientUserAgent !== null && preg_match('/((^(xunlei?).?\d+.\d+.\d+.\d+)|cacao_torrent)/i', $clientUserAgent) === 1) || preg_match('/^-(UW\w{4}|SP(([0-2]\d{3})|(3[0-5]\d{2})))-/i', $clientPeerID) === 1) {
	die(GenerateBencode(array('failure reason' => ErrorMessage[6])));
}
$clientInfoHash = strtolower(bin2hex($clientInfoHash));
//$clientPeerID = bin2hex($clientPeerID);
if (strlen($clientInfoHash) !== 40 || strlen($clientPeerID) < 12 || strlen($clientPeerID) > 20 || $clientInfoHash === $clientPeerID || preg_match('/(.)\1{32}/i', $clientInfoHash) === 1 || preg_match('/(.)\1{12}/i', $clientPeerID) === 1) {
	die(GenerateBencode(array('failure reason' => ErrorMessage[3])));
}
/*
if ($clientInfoHash !== '457b1b9f117d95cf7982b6deb6c5fdd14f3f0180') {
	die(GenerateBencode(array('failure reason' => '服务器无法验证这个种子, 可能是因为它没有被注册. (EC: 4)')));
}$
*/
$clientLeft = $_GET['left'] ?? null;
$clientType = 0;
if ($clientLeft !== null) {
	$clientType = (intval($clientLeft) === 0) ? 2 : 1;
}
if ($clientUserAgent !== null && stripos($clientUserAgent, 'bitcomet') !== false && (($bcClientVersion = strstr($clientUserAgent, '/')) === false || strlen($bcClientVersion) < 2 || (($bcClientVersion = explode('.', substr($bcClientVersion, 1))) && count($bcClientVersion) < 2) || !is_numeric($bcClientVersion[0]) || !is_numeric($bcClientVersion[1]) || intval($bcClientVersion[0]) < 1 || (intval($bcClientVersion[0]) === 1 && intval($bcClientVersion[1]) < 82))) {
	if ($clientType !== 2) {
		die(GenerateBencode(array('failure reason' => ErrorMessage[7])));
	}
	$resBencodeArr['warning message'] = WarningMessage[3];
} else if (isset($_SERVER['HTTP_WANT_DIGEST']) && !(($clientUserAgent !== null && stripos($clientUserAgent, 'aria2') !== false) || stripos($clientPeerID, 'A2') === 0)) {
	if ($clientType !== 2) {
		die(GenerateBencode(array('failure reason' => ErrorMessage[6])));
	}
	$resBencodeArr['warning message'] = WarningMessage[4];
}
$db = @new MySQLi(DBPAddress, DBUser, DBPass, DBName, DBPort, DBSocket);
if ($db->connect_errno > 0) {
	die(GenerateBencode(array('failure reason' => ErrorMessage[1])));
}
$debugLevel = 0;
$premiumUser = false;
if (isset($_GET['debug'])) {
	$debugLevel = CheckKeyAvailability($_GET['debug']);
	if ($debugLevel === 0) {
		die(GenerateBencode(array('failure reason' => ErrorMessage[8])));
	}
	if ($debugLevel === 10) {
		$premiumUser = true;
	}
} else if (isset($_GET['p'])) {
	if (CheckKeyAvailability($_GET['p']) <= 1) {
		die(GenerateBencode(array('failure reason' => ErrorMessage[8])));
	}
	$premiumUser = true;
}
$resBencodeArr = array('interval' => AnnounceInterval, 'min interval' => AnnounceMinInterval, 'complete' => 0, 'incomplete' => 0, 'downloaded' => 0, 'peers' => array());
if ($premiumUser) {
	$resBencodeArr['interval'] = PremiumAnnounceInterval;
	$resBencodeArr['min interval'] = PremiumAnnounceMinInterval;
}
$clientCompact = (isset($_GET['compact']) && intval($_GET['compact']) === 1) ? true : false;
if ($clientCompact) {
	$resBencodeArr['peers'] = '';
	$resBencodeArr['peers6'] = '';
}
$curTime = time();
#$db->set_charset('utf8mb4');
#$db->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
#$db->query('DELETE FROM Peers WHERE last_timestamp < DATE_SUB(NOW(), INTERVAL 12 HOUR) LIMIT 500');
$escapedClientInfoHash = $db->escape_string($clientInfoHash);
$escapedClientPeerID = $db->escape_string($clientPeerID);
if (($clientUserAgent !== null && (stripos($clientUserAgent, 'qbittorrent') !== false || stripos($clientUserAgent, 'bitcomet') !== false)) || stripos($clientPeerID, '-QB') === 0 || stripos($clientPeerID, '-BC') === 0) {
	$noWarnClient = true;
	if (stripos($clientPeerID, '-QB') === 0) {
		$mainClientVersion = hexdec($clientPeerID[3]);
		$sub1ClientVersion = hexdec($clientPeerID[4]);
		$sub2ClientVersion = hexdec($clientPeerID[5]);
	} else if ($clientUserAgent !== null && stripos($clientUserAgent, 'qbittorrent') !== false) {
		$clientVerStr = strstr($clientUserAgent, '/');
		if ($clientVerStr === false) {
			$clientVerStr = strstr($clientUserAgent, ' ');
			if (stripos($clientVerStr, 'enhanced') !== false) {
				$clientVerStr = strstr($clientVerStr, ' ');
			}
		}
		if ($clientVerStr !== false) {
			$clientVerExplode = explode('.', substr($clientVerStr, 1));
			if (count($clientVerExplode) > 2 && is_numeric($clientVerExplode[0]) && is_numeric($clientVerExplode[1])) {
				$mainClientVersion = intval($clientVerExplode[0]);
				$sub1ClientVersion = intval($clientVerExplode[1]);
				if (is_numeric($clientVerExplode[2]) || (strlen($clientVerExplode[2]) > 1 && is_numeric(($clientVerExplode[2] = $clientVerExplode[2][0])))) {
					$sub2ClientVersion = intval($clientVerExplode[2]);
				}
			}
		}
	}
	if (isset($mainClientVersion, $sub1ClientVersion, $sub2ClientVersion) && $mainClientVersion !== false && $sub1ClientVersion !== false && $sub2ClientVersion !== false && ($mainClientVersion > 4 || ($mainClientVersion === 4 && ($sub1ClientVersion > 3 || ($sub1ClientVersion === 3 && $sub2ClientVersion > 6))))) {
		$noWarnClient = false;
	}
	if ($noWarnClient) {
		$intervalCompareDate = date('Y-m-d H:i', ($curTime - $resBencodeArr['interval'])) . ':00';
		$noWarnClientMinIntervalCompareDate = date('Y-m-d H:i:s', ($curTime + ceil($resBencodeArr['interval'] / 10) - ceil($resBencodeArr['interval'] / 3)));
		$noWarnClientAnnounceIntervalCheck = $db->query("(SELECT 1 FROM Peers_1 WHERE info_hash = '{$escapedClientInfoHash}' AND peer_id = '{$escapedClientPeerID}' AND last_timestamp > '{$intervalCompareDate}' AND last_timestamp < '{$noWarnClientMinIntervalCompareDate}' LIMIT 1) UNION ALL (SELECT 1 FROM Peers_2 WHERE info_hash = '{$escapedClientInfoHash}' AND peer_id = '{$escapedClientPeerID}' AND last_timestamp > '{$intervalCompareDate}' AND last_timestamp < '{$noWarnClientMinIntervalCompareDate}' LIMIT 1) LIMIT 1");
		if ($noWarnClientAnnounceIntervalCheck->num_rows > 0) {
			die(GenerateBencode(array('failure reason' => (isset($resBencodeArr['warning message']) ? $resBencodeArr['warning message'] : ServerMessage))));
		}
		$noWarnClientAnnounceIntervalCheck->close();
		$resBencodeArr['interval'] = intval(ceil($resBencodeArr['interval'] / 2));
		$resBencodeArr['min interval'] = $resBencodeArr['interval'];
	} else if (isset($mainClientVersion, $sub1ClientVersion, $sub2ClientVersion) && $mainClientVersion !== false && $sub1ClientVersion !== false && $sub2ClientVersion !== false && ($mainClientVersion === 4 && $sub1ClientVersion === 5 && $sub2ClientVersion < 2)) {
		$qB45WarningMessage = 'qBittorrent 4.5 系列 Web UI 暴出未授权任意文件访问漏洞, 将影响安全性. 如果你正在使用该系列客户端, 请考虑升级 4.5.2/关闭 Web UI 对外访问/降级.';
		$resBencodeArr['warning message'] = (!isset($resBencodeArr['warning message']) ? $qB45WarningMessage : "{$qB45WarningMessage} | {$resBencodeArr['warning message']}");
	}
}
$torrentSeeder = 0;
$torrentLeecher = 0;
$torrentDownloadedQuery = $db->query("SELECT total_completed FROM Torrents WHERE info_hash = '{$escapedClientInfoHash}' LIMIT 1");
$torrentDownloaded = ($torrentDownloadedQuery !== false && ($torrentDownloadedResult = $torrentDownloadedQuery->fetch_row()) !== false && $torrentDownloadedResult !== null) ? intval($torrentDownloadedResult[0]) : 0;
$clientNumwant = (isset($_GET['numwant']) && is_numeric($_GET['numwant'])) ? intval($_GET['numwant']) : 50;
if ($clientNumwant < 1 || $clientNumwant > 1000) {
	$clientNumwant = 50;
}
$clientNoPeerID = (isset($_GET['no_peer_id']) && intval($_GET['no_peer_id']) === 1) ? true : false;
$basicPeerQuerySQL = 'SELECT peer_id, last_timestamp, last_type, ipv4, ipv6, port';
$table1PeerQuerySQL = "{$basicPeerQuerySQL} FROM Peers_1 WHERE info_hash = ? AND peer_id != ? AND (last_event IS NULL OR (last_event != 'stopped' AND last_event != 'paused'))";
$table2PeerQuerySQL = "{$basicPeerQuerySQL} FROM Peers_2 WHERE info_hash = ? AND peer_id != ? AND (last_event IS NULL OR (last_event != 'stopped' AND last_event != 'paused'))";
$compareSQL = ' AND last_timestamp >= \'' . date('Y-m-d H:i', ($curTime - AnnounceMaxInterval)) . ':00\'';
if (OldDBName === 'Peers_1') {
	$table1PeerQuerySQL .= $compareSQL;
} else {
	$table2PeerQuerySQL .= $compareSQL;
}
$peerQuerySTMT = $db->prepare("({$table1PeerQuerySQL} ORDER BY last_timestamp DESC LIMIT ?) UNION ALL ({$table2PeerQuerySQL} ORDER BY last_timestamp DESC LIMIT ?) ORDER BY last_timestamp DESC LIMIT ?");
$peerQuerySTMT->bind_param('ssissii', $clientInfoHash, $clientPeerID, $clientNumwant, $clientInfoHash, $clientPeerID, $clientNumwant, $clientNumwant);
$peerQuerySTMT->bind_result($peerID, $peerLastTimestamp, $peerType, $peerIPv4Str, $peerIPv6Str, $peerPort);
$peerQuerySTMT->execute();
while ($peerQuerySTMT->fetch()) {
	if (empty($peerID)) {
		continue;
	}
	if ($peerType === 2) {
		$torrentSeeder++;
	} else if ($peerType === 1) {
		$torrentLeecher++;
	}
	if ($peerPort !== 0 && $peerPort !== 1) {
		if (!empty($peerIPv4Str)) {
			$peerIPv4List = explode(',', $peerIPv4Str);
			foreach ($peerIPv4List as $peerIPv4) {
				if ($clientCompact) {
					$resBencodeArr['peers'] .= inet_pton($peerIPv4) . pack('n', $peerPort);
					continue;
				}
				$tArr = array();
				if (!$clientNoPeerID) {
					$tArr['peer_id'] = $peerID;
				}
				$tArr['ip'] = $peerIPv4;
				$tArr['port'] = $peerPort;
				$resBencodeArr['peers'][] = $tArr;
			}
		}
		if (!empty($peerIPv6Str)) {
			$peerIPv6List = explode(',', $peerIPv6Str);
			foreach ($peerIPv6List as $peerIPv6) {
				if ($clientCompact) {
					$resBencodeArr['peers6'] .= inet_pton($peerIPv6) . pack('n', $peerPort);
					continue;
				}
				$tArr = array();
				if (!$clientNoPeerID) {
					$tArr['peer_id'] = $peerID;
				}
				$tArr['ip'] = $peerIPv6;
				$tArr['port'] = $peerPort;
				$resBencodeArr['peers'][] = $tArr;
			}
		}
	}
}
$peerQuerySTMT->close();
if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
	$clientIP = $_SERVER['HTTP_CF_CONNECTING_IP'];
} else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	// Amazon CloudFront
	#$clientIP = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
	// Cloudflare
	/*
	$clientIPList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']); // 临时使用, 其它代理 IP 不应纳入列表.
	$clientIP = $clientIPList[((count($clientIPList) > 1) ? 1 : 0)];
	*/
	$clientIP = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
} else if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
	$clientIP = explode(',', $_SERVER['HTTP_CLIENT_IP'])[0];
} else {
	$clientIP = $_SERVER['REMOTE_ADDR'];
}
/*
$clientIPList = $_GET['ip'] ?? null;
$clientIPv4List = $_GET['ipv4'] ?? null;
$clientIPv6List = $_GET['ipv6'] ?? null;
$clientIPsList = $_GET['ips'] ?? null;
*/
$clientPort = $_GET['port'] ?? 0;
if (($clientPort = intval($clientPort)) > 1 && $clientPort < 65536) {
	$validClientIPList = array();
	#AddIPToArr($validClientIPList, $clientIPList, $clientIPv4List, $clientIPv6List, $clientIPsList);
	# !isset($validClientIPList['ipv4']), !isset($validClientIPList['ipv6'])
	if (filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
		$validClientIPList['ipv4'][] = $clientIP;
	} else if (filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
		$validClientIPList['ipv6'][] = $clientIP;
	}
} else {
	if ($clientPort !== 1) {
		$clientPort = 0;
	}
	$portMessage = WarningMessage[1];
	$resBencodeArr['warning message'] = ((!isset($resBencodeArr['warning message'])) ? $portMessage : "{$portMessage} | {$resBencodeArr['warning message']}");
}
/*
加入客户端真实 IP 后必定会有 IP 地址.
if (!isset($validClientIPList) || count($validClientIPList) < 1) {
	$resBencodeArr['warning message'] = '服务器无法验证或你的客户端没有上报任何有效的 IP 地址, 因此你的速度会受到影响. (WC: 1)';
}
*/
#$clientUploaded = $_GET['uploaded'] ?? 0;
#$clientDownloaded = $_GET['downloaded'] ?? 0;
/*
if ($clientUploaded !== null && $clientDownloaded !== null) {
	$clientUploaded = intval($clientUploaded);
	$clientDownloaded = intval($clientDownloaded);
} else {
	if (!isset($resBencodeArr['warning message'])) {
		$resBencodeArr['warning message'] = '';
	} else {
		$resBencodeArr['warning message'] .= '. 另一问题是, ';
	}
	$resBencodeArr['warning message'] .= '服务器无法接收到你的上传或下载数据, 因此无法进行统计. (WC: 2)';
}
*/
$resBencodeArr['complete'] = $torrentSeeder;
$resBencodeArr['incomplete'] = $torrentLeecher;
$resBencodeArr['downloaded'] = $torrentDownloaded;
if ($clientType !== 0) {
	$minIntervalCompareDate = date('Y-m-d H:i:s', ($curTime - $resBencodeArr['min interval'] + ceil($resBencodeArr['min interval'] / 10)));
	$clientReannounceQuery = $db->query("(SELECT ipv4, ipv6 FROM Peers_1 WHERE info_hash = '{$escapedClientInfoHash}' AND peer_id = '{$escapedClientPeerID}' AND last_event " . (($clientEvent === null) ? 'IS NULL' : "= '{$clientEvent}'") . " AND last_type = {$clientType} AND last_timestamp > '{$minIntervalCompareDate}' LIMIT 1) UNION ALL (SELECT ipv4, ipv6 FROM Peers_2 WHERE info_hash = '{$escapedClientInfoHash}' AND peer_id = '{$escapedClientPeerID}' AND last_event " . (($clientEvent === null) ? 'IS NULL' : "= '{$clientEvent}'") . " AND last_type = {$clientType} AND last_timestamp > '{$minIntervalCompareDate}' LIMIT 1) LIMIT 1");
	$clientIsReannounced = ($clientReannounceQuery->num_rows > 0);
	if ($clientIsReannounced && ($clientReannounceResult = $clientReannounceQuery->fetch_row()) !== false && $clientReannounceResult !== null) {
		if (!empty($clientReannounceResult[0])) {
			$clientReannounceIPv4List = explode(',', $clientReannounceResult[0]);
			$validClientIPList['ipv4'] = isset($validClientIPList['ipv4']) ? array_unique(array_merge($validClientIPList['ipv4'], $clientReannounceIPv4List)) : $clientReannounceIPv4List;
		}
		if (!empty($clientReannounceResult[1])) {
			$clientReannounceIPv6List = explode(',', $clientReannounceResult[1]);
			$validClientIPList['ipv6'] = isset($validClientIPList['ipv6']) ? array_unique(array_merge($validClientIPList['ipv6'], $clientReannounceIPv6List)) : $clientReannounceIPv6List;
		}
	}
	// 出于 IPv4/IPv6 多重回报, 目前不阻止重复回报更新 Peer 记录.
	$clientIPv4String = isset($validClientIPList['ipv4']) ? implode(',', $validClientIPList['ipv4']) : null;
	$clientIPv6String = isset($validClientIPList['ipv6']) ? implode(',', $validClientIPList['ipv6']) : null;
	if (strlen($clientIPv4String) > 70 || strlen($clientIPv6String) > 560) {
		$clientIPv4String = (isset($clientReannounceIPv4List) ? implode(',', $clientReannounceIPv4List) : null);
		$clientIPv6String = (isset($clientReannounceIPv6List) ? implode(',', $clientReannounceIPv6List) : null);
	}
	$db->query("DELETE FROM " . OldDBName . " WHERE info_hash = '{$escapedClientInfoHash}' AND peer_id = '{$escapedClientPeerID}' LIMIT 1");
	$peerInsertSTMT = $db->prepare("INSERT INTO " . CurDBName . " (info_hash, peer_id, user_agent, last_event, last_type, ipv4, ipv6, port) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_agent = VALUES(user_agent), last_event = VALUES(last_event), last_type = VALUES(last_type), ipv4 = IFNULL(VALUES(ipv4), ipv4), ipv6 = IFNULL(VALUES(ipv6), ipv6), port = VALUES(port)");
	$peerInsertSTMT->bind_param('ssssissi', $clientInfoHash, $clientPeerID, $clientUserAgent, $clientEvent, $clientType, $clientIPv4String, $clientIPv6String, $clientPort);
	$peerInsertSTMT->execute();
	$peerInsertSTMT->close();
	if (!$clientIsReannounced && $clientEvent === 'completed') {
		#$torrentSTMT = $db->prepare("INSERT INTO Torrents (info_hash, total_completed, total_uploaded, total_downloaded) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE total_completed = total_completed + VALUES(total_completed), total_uploaded = total_uploaded + VALUES(total_uploaded), total_downloaded = total_downloaded + VALUES(total_downloaded)");
		/*
		$torrentSTMT = $db->prepare("INSERT INTO Torrents (info_hash, total_completed) VALUES (?, ?) ON DUPLICATE KEY UPDATE total_completed = total_completed + VALUES(total_completed)");
		#$torrentSTMT->bind_param('siii', $clientInfoHash, $newCompleted, $clientUploaded, $clientDownloaded);
		$torrentSTMT->bind_param('si', $clientInfoHash, $newCompleted);
		$torrentSTMT->execute();
		$torrentSTMT->close();
		*/
		#$newCompleted = ($clientEvent === 'completed' ? 1 : 0);
		$torrentQuery = $db->query("INSERT INTO Torrents (info_hash, total_completed) VALUES ('{$escapedClientInfoHash}', 1) ON DUPLICATE KEY UPDATE total_completed = total_completed + VALUES(total_completed)");
	}
}
$db->close();
/*
我们不想知道这些东西.
$key = $_GET['key'] ?? null;
$corrupt = $_GET['corrupt'] ?? null;
$redundant = $_GET['redundant'] ?? null;
*/
if (!isset($resBencodeArr['warning message'])) {
	if ($debugLevel === 0 && ($clientUserAgent !== null && stripos($clientUserAgent, 'transmission') !== false) || stripos($clientPeerID, '-TR') === 0) {
		$resBencodeArr['interval'] *= 2;
		$resBencodeArr['min interval'] *= 2;
	} else {
		$resBencodeArr['warning message'] = ServerMessage;
	}
} else {
	$resBencodeArr['warning message'] .= ' | ' . ServerMessage;
}
switch ($debugLevel) {
	case 10:
		$resBencodeArr['warning message'] = sprintf(
			"Debug 高级信息/IPv4: %s, IPv6: %s, 端口: %d, 客户端: %s (Peer ID: %s), 事件: %s, 加密支持: %s, 紧凑模式: %s, 省略其它客户端的 Peer ID: %s, 当前回报间隔: %u, 最小回报间隔: %u | {$resBencodeArr['warning message']}",
			($clientIPv4String ?? '空'),
			($clientIPv6String ?? '空'),
			($clientPort ?? -1),
			($clientUserAgent ?? '空'),
			(!empty($clientPeerID) ? substr($clientPeerID, 0, 8) : '空'),
			(!empty($clientEvent) ? $clientEvent : '空'),
			($clientSupportCrypto ? '是' : '否'),
			($clientCompact ? '是' : '否'),
			(($clientCompact || $clientNoPeerID) ? '是' : '否'),
			$resBencodeArr['interval'],
			($resBencodeArr['min interval'] ?? $resBencodeArr['interval'])
		);
		break;
	case 1:
		$resBencodeArr['warning message'] = sprintf(
			"Debug 基本信息/IPv4: %s, IPv6: %s, 客户端: %s | {$resBencodeArr['warning message']}",
			($clientIPv4String ?? '空'),
			($clientIPv6String ?? '空'),
			($clientUserAgent ?? '空')
		);
		break;
	case 0:
	default:
		break;
}
echo GenerateBencode($resBencodeArr);
?>
