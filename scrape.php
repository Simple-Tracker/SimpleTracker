<?php
require_once('config.php');
require_once('Bencode.php');
use \PureBencode\Bencode;
header('Content-Type: text/plain; charset=utf-8');
if (isset($_SERVER['QUERY_STRING'])) {
	preg_match_all('/info_hash=([^&]*)/i', urldecode($_SERVER['QUERY_STRING']), $info_hash_match);
	$clientInfoHashList = array_map(function ($v) { $v = strtolower(bin2hex($v)); if (strlen($v) !== 40) { die(Bencode::encode(array('failure reason' => ErrorMessage[3]))); } return $v; }, $info_hash_match[1]);
}
if (!isset($rawInfoHashList) || count($rawInfoHashList) < 1 || count($clientInfoHashList) < 1) {
	die(Bencode::encode(array('failure reason' => ErrorMessage[2])));
} else if (count($clientInfoHashList) > 100) {
	die(Bencode::encode(array('failure reason' => ErrorMessage[4])));
} else if (!empty($_SERVER['HTTP_USER_AGENT']) && (preg_match('/((^(xunlei?).?\d+.\d+.\d+.\d+)|cacao_torrent)/i', $_SERVER['HTTP_USER_AGENT']) === 1)) {
	die(Bencode::encode(array('failure reason' => ErrorMessage[6])));
}
if (DBPort === null) {
	die(Bencode::encode(array('failure reason' => ErrorMessage[11])));
} else {
	$db = @new MySQLi(DBPAddress, DBUser, DBPass, DBName, DBPort, DBSocket);
	if ($db->connect_errno > 0) {
		die(Bencode::encode(array('failure reason' => ErrorMessage[1])));
	}
}
/*
try {
	$cache = new Redis();
	if (CachePersistence) {
		$cache->pconnect(CacheAddress, CachePort, CacheTimeout);
	} else {
		$cache->connect(CacheAddress, CachePort, CacheTimeout);
	}
	if (CacheAuth !== null) {
		$cache->auth(CacheAuth);
	}
	if ($cache->ping() !== true) {
		die(Bencode::encode(array('failure reason' => ErrorMessage[1])));
	}
} catch (Exception $e) {
	die(Bencode::encode(array('failure reason' => ErrorMessage[1])));
}
*/
$resBencodeArr = array('files' => array(), 'flags' => array('min_request_interval' => ScrapeMinInterval));
foreach ($clientInfoHashList as $clientInfoHash) {
	$escapeValue = $db->escape_string($clientInfoHash);
	$queryWhereSQL .= "info_hash = '{$escapeValue}' OR ";
	$resBencodeArr['files'][hex2bin($clientInfoHash)]['downloaded'] = 0;
}
$queryWhereSQL = substr($queryWhereSQL, 0, -4) . ' LIMIT ' . count($clientInfoHashList);
$torrentTotalCompletedList = $db->query("SELECT info_hash, total_completed FROM Torrents {$queryWhereSQL}");
while ($torrentTotalCompleted = $torrentTotalCompletedList->fetch_assoc()) {
	if (empty($torrentTotalCompleted['info_hash'])) {
		continue;
	}
	$resBencodeArr['files'][hex2bin($torrentTotalCompleted['info_hash'])]['downloaded'] = $torrentTotalCompleted['total_completed'];
}
$db->close();
/*
if (!CachePersistence) {
	$cache->close();
}
*/
echo Bencode::encode($resBencodeArr);
?>
