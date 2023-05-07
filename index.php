<?php
if (isset($_GET['info_hash']) || isset($_GET['peer_id']) || isset($_GET['event'])) {
	require_once('include.bencode.php');
	header('Content-Type: text/plain; charset=utf-8');
	die(GenerateBencode(array('failure reason' => '服务器认为 Tracker 地址有误. (EC: 5)')));
}
define('BotUAKeywords', array('bot', 'crawl', 'spider' ,'slurp', 'sohu-search', 'lycos', 'robozilla'));
function IsRobot() {
	if (!isset($_SERVER['HTTP_USER_AGENT']) || empty($_SERVER['HTTP_USER_AGENT'])) {
		return false;
	}
	$ua = strtolower($_SERVER['HTTP_USER_AGENT']);
	foreach (BotUAKeywords as $botUAKeyword) {
		if (stripos($ua, $botUAKeyword) !== false) {
			return true;
		}
	}
	return false;
}
function OutputHTMLEnd(string $str) {
	echo rtrim($str);
	#echo "</pre>\n		<script defer src='https://static.cloudflareinsights.com/beacon.min.js' data-cf-beacon='{\"token\": \"3dedd9b54b734ae7ac35370ce07ba293\"}'></script>\n	</body>\n</html>\n";
	echo "</pre>\n	</body>\n</html>\n";
}
header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html>\n<html>\n	<head>\n		<meta charset=\"utf-8\">\n		<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n		<link rel=\"stylesheet\" href=\"dark.css\">\n		<link rel=\"shortcut icon\" href=\"favicon.ico\" type=\"image/x-icon\">\n		<title>Tracker 状态信息 - Simple Tracker</title>\n	</head>\n	<body>\n		<pre>";
echo "Simple Tracker [Version: 2023-08-07]\n";
echo "服务器 Telegram 频道: https://t.me/SimpleTracker\n";
echo "服务器 Telegram 群组 (反馈与交流 BitTorrent 相关内容): https://t.me/SimpleTrackerGroup\n";
echo "服务器 Tracker URL: https://t1.hloli.org/announce\n";
echo "服务器 Tracker 现禁止以下客户端 (不尽如此): 迅雷/旋风/磁力云/影音先锋/BitTorrent Media Player\n";
echo "公共 Tracker 列表: https://t1.hloli.org/tracker.txt\n\n";
/*
$curTime = time();
$cacheMode = ((!isset($_GET['nocache']) || $_GET['nocache'] !== 'NoCache-SimpleTracker') && is_file('indexCache-MySQL.txt'));
$cacheExpired = (!$cacheMode || (filemtime('indexCache-MySQL.txt') + 1800) < $curTime);
$fpmMode = function_exists('fastcgi_finish_request');
if ($cacheMode && (IsRobot() || !$cacheExpired)) {
	$cacheContent = file_get_contents('indexCache-MySQL.txt');
	if (!empty($cacheContent)) {
		OutputHTMLEnd($cacheContent);
		if (!$cacheExpired) {
			die();
		} else if ($fpmMode) {
			fastcgi_finish_request();
		}
	}
} else if (is_file('indexCacheLock') && (filemtime('indexCacheLock') + 60) >= $curTime) {
	OutputHTMLEnd("正在构建缓存...\n");
	die();
}
*/
$cacheContent = @file_get_contents('indexCache-Redis.txt');
OutputHTMLEnd((!empty($cacheContent) ? $cacheContent : "当前缓存出现故障. :(\n"));
?>
