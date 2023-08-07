<?php
require_once('config.php');
require_once('include.bencode.php');
ini_set('memory_limit', '512M');
header('Content-Type: text/plain; charset=utf-8');
if (isset($_SERVER['QUERY_STRING'])) {
	preg_match_all('/info_hash=([^&]*)/i', urldecode($_SERVER['QUERY_STRING']), $info_hash_match);
	$rawInfoHashList = $info_hash_match[1];
	$clientInfoHashList = array_map(function ($v) { $v = strtolower(bin2hex($v)); if (strlen($v) !== 40) { die(GenerateBencode(array('failure reason' => ErrorMessage[3]))); } return $v; }, $rawInfoHashList);
}
$premiumUser = false;
$clientNumwant = 50;
/*
全部列出的资源消耗需要优化 (输出结果需要进行缓存, 最好在 autoclean 进行).
if (!isset($rawInfoHashList) && isset($_GET['k'])) {
	if ($_GET['k'] === 'ak-Debug' || (strpos($_GET['k'], 'uk-') === 0 && strpos($_GET['k'], '.') === false && is_file("UserKey-SimpleTracker/{$_GET['p']}"))) {
		$premiumUser = true;
	} else {
		die(GenerateBencode(array('failure reason' => ErrorMessage[8])));
	}
}
*/
if (!$premiumUser) {
	if (!isset($rawInfoHashList) || count($rawInfoHashList) < 1 || count($clientInfoHashList) < 1) {
		die(GenerateBencode(array('failure reason' => ErrorMessage[2])));
	} else if (count($clientInfoHashList) > 100) {
		die(GenerateBencode(array('failure reason' => ErrorMessage[4])));
	} else if (!empty($_SERVER['HTTP_USER_AGENT']) && (preg_match('/((^(xunlei?).?\d+.\d+.\d+.\d+)|cacao_torrent)/i', $_SERVER['HTTP_USER_AGENT']) === 1)) {
		die(GenerateBencode(array('failure reason' => ErrorMessage[6])));
	}
}
$db = @new MySQLi(DBPAddress, DBUser, DBPass, DBName, DBPort, DBSocket);
if ($db->connect_errno > 0) {
	die(GenerateBencode(array('failure reason' => ErrorMessage[1])));
}
$cache = new Redis(array(
	'host' => CacheAddress,
	'port' => CachePort,
	'persistent' => CachePersistence,
	'auth' => CacheAuth,
	'connectTimeout' => CacheTimeout
));
if ($cache->ping() !== true) {
	die(GenerateBencode(array('failure reason' => ErrorMessage[1])));
}
$resBencodeArr = array('files' => array(), 'flags' => array('min_request_interval' => ScrapeMinInterval));
$filesArr = array();
if ($premiumUser) {
	$torrentResult = $db->query('SELECT info_hash, total_completed FROM Torrents ORDER BY total_completed DESC', MYSQLI_USE_RESULT);
	while ($torrentRow = $torrentResult->fetch_assoc()) {
		if (empty($torrentRow['info_hash'])) {
			continue;
		}
		$filesArr[hex2bin($torrentRow['info_hash'])] = array('downloaded' => $torrentRow['total_completed']);
	}
} else {
	$queryWhereSQL = 'WHERE ';
	$table1TimestampWhereSQL = '';
	$table2TimestampWhereSQL = '';
	$compareSQL = ' AND last_timestamp >= \'' . date('Y-m-d H:i', time() - AnnounceMaxInterval) . ':00\'';
	if (OldDBName === 'Peers_1') {
		$table1TimestampWhereSQL = $compareSQL;
	} else {
		$table2TimestampWhereSQL = $compareSQL;
	}
	foreach ($clientInfoHashList as $clientInfoHash) {
		$torrentSeeder = 0;
		$torrentLeecher = 0;
		$clientInfoHashPeerIDList = $cache->zRevRangeByScore("IH:{$clientInfoHash}", '+inf', 0, ['limit' => [0, $clientNumwant]]);
		foreach ($clientInfoHashPeerIDList as $clientInfoHashPeerID) {
			if (empty($clientInfoHashPeerID)) {
				continue;
			}
			$clientInfoHashPeerTypeAndEvent = $cache->get("IP:{$clientInfoHash}+{$clientInfoHashPeerID}:TE");
			if ($clientInfoHashPeerTypeAndEvent === false) {
				continue;
			}
			$clientInfoHashPeerType = explode(':', $clientInfoHashPeerTypeAndEvent, 2)[0];
			$clientInfoHashPeerType = intval($clientInfoHashPeerType);
			if ($clientInfoHashPeerType === 2) {
				$torrentSeeder++;
			} else if ($clientInfoHashPeerType === 1) {
				$torrentLeecher++;
			}
		}
		$escapeValue = $db->escape_string($clientInfoHash);
		$queryWhereSQL .= "info_hash = '{$escapeValue}' OR ";
		$filesArr[hex2bin($clientInfoHash)] = array('complete' => $torrentSeeder, 'incomplete' => $torrentLeecher, 'downloaded' => 0);
	}
	$queryWhereSQL = substr($queryWhereSQL, 0, -4) . ' LIMIT ' . count($clientInfoHashList);
	$torrentTotalCompletedList = $db->query("SELECT info_hash, total_completed FROM Torrents {$queryWhereSQL}");
	while ($torrentTotalCompleted = $torrentTotalCompletedList->fetch_assoc()) {
		if (empty($torrentTotalCompleted['info_hash'])) {
			continue;
		}
		$filesArr[hex2bin($torrentTotalCompleted['info_hash'])]['downloaded'] = $torrentTotalCompleted['total_completed'];
	}
}
$db->close();
if (!CachePersistence) {
	$cache->close();
}
$resBencodeArr['files'] = $filesArr;
echo GenerateBencode($resBencodeArr);
?>
