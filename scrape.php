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
/*
全部列出的资源消耗需要优化 (输出结果需要进行缓存).
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
	if ($curHour === 1 || $curHour === 3 || $curHour === 5 || $curHour === 7 || $curHour === 9 || $curHour === 11) {
		$table1TimestampWhereSQL = $compareSQL;
	} else {
		$table2TimestampWhereSQL = $compareSQL;
	}
	foreach ($clientInfoHashList as $value) {
		$escapeValue = $db->escape_string($value);
		$queryWhereSQL .= "info_hash = '{$escapeValue}' OR ";
		$torrentSeeder = $db->query("SELECT SUM(count_seeder) FROM ((SELECT COUNT(1) as count_seeder FROM Peers_1 WHERE last_type = 2 AND (last_event IS NULL OR (last_event != 'stopped' AND last_event != 'paused')) AND info_hash = '{$escapeValue}' {$table1TimestampWhereSQL}) UNION ALL (SELECT COUNT(1) as count_seeder FROM Peers_2 WHERE last_type = 2 AND (last_event IS NULL OR (last_event != 'stopped' AND last_event != 'paused')) AND info_hash = '{$escapeValue}' {$table2TimestampWhereSQL})) as total_seeder");
		if ($torrentSeeder === false) {
			$torrentSeeder = 0;
		} else {
			$torrentSeederRow = $torrentSeeder->fetch_row();
			$torrentSeeder = ($torrentSeederRow !== false && $torrentSeederRow !== null) ? intval($torrentSeederRow[0]) : 0;
		}
		$torrentLeecher = $db->query("SELECT SUM(count_leecher) FROM ((SELECT COUNT(1) as count_leecher FROM Peers_1 WHERE last_type = 1 AND (last_event IS NULL OR (last_event != 'stopped' AND last_event != 'paused')) AND info_hash = '{$escapeValue}' {$table1TimestampWhereSQL}) UNION ALL (SELECT COUNT(1) as count_leecher FROM Peers_2 WHERE last_type = 1 AND (last_event IS NULL OR (last_event != 'stopped' AND last_event != 'paused')) AND info_hash = '{$escapeValue}' {$table2TimestampWhereSQL})) as total_leecher");
		if ($torrentLeecher === false) {
			$torrentLeecher = 0;
		} else {
			$torrentLeecherRow = $torrentLeecher->fetch_row();
			$torrentLeecher = ($torrentLeecherRow !== false && $torrentLeecherRow !== null) ? intval($torrentLeecherRow[0]) : 0;
		}
		$filesArr[hex2bin($value)] = array('complete' => $torrentSeeder, 'incomplete' => $torrentLeecher, 'downloaded' => 0);
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
$resBencodeArr['files'] = $filesArr;
echo GenerateBencode($resBencodeArr);
?>
