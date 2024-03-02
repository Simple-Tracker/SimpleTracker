<?php
use PureBencode\Bencode;
define('BotUAKeywords', array('bot', 'crawl', 'spider' ,'slurp', 'sohu-search', 'lycos', 'robozilla'));
define('RespStr', "<!DOCTYPE html>\n<html>\n	<head>\n		<meta charset=\"utf-8\">\n		<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n		<link rel=\"stylesheet\" href=\"dark.css\">\n		<link rel=\"shortcut icon\" href=\"favicon.ico\" type=\"image/x-icon\">\n		<title>Tracker 状态信息 - Simple Tracker</title>\n	</head>\n	<body>\n		<pre>Simple Tracker [Version: 2023-08-18]\n服务器 Telegram 频道: https://t.me/SimpleTracker\n服务器 Telegram 频道: https://t.me/SimpleTracker\n服务器 Telegram 群组 (反馈与交流 BitTorrent 相关内容): https://t.me/SimpleTrackerGroup\n服务器 Tracker URL: https://t1.hloli.org/announce\n服务器 Tracker 现禁止以下客户端 (不尽如此): 迅雷/旋风/磁力云/影音先锋/BitTorrent Media Player\n公共 Tracker 列表: https://t1.hloli.org/tracker.txt\n\n");
function ProcessIndex(Swoole\Http\Request $request, Swoole\Http\Response $response) {
	if (isset($request->get['info_hash']) || isset($request->get['peer_id']) || isset($request->get['event'])) {
		$response->header('Content-Type', 'text/plain; charset=utf-8', false);
		$response->end(Bencode::encode(array('failure reason' => '服务器认为 Tracker 地址有误. (EC: 5)')));
		return;
	}
	$cacheContent = @file_get_contents('indexCache-Redis.txt');
	$response->header('Content-Type', 'text/html; charset=utf-8', false);
	$response->end(RespStr . (rtrim((!empty($cacheContent) ? $cacheContent : "当前缓存出现故障. :(")) . "</pre>\n	</body>\n</html>\n"));
}
?>
