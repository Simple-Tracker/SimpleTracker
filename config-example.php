<?php
//chdir(__DIR__);
date_default_timezone_set('Asia/Shanghai');
define('LogDir', 'logs-Custom');

// Database Config
mysqli_report(MYSQLI_REPORT_OFF);
define('DBAddress', 'localhost');
define('DBPAddress', 'p:localhost');
define('DBPort', 3306); // Port or null.
define('DBUser', '');
define('DBPass', '');
define('DBName', '');
define('DBSocket', '/var/lib/mysql/mysql.sock'); // Socket or null.
define('DBRetryWaitTime', 10);
$curHour = intval(date('h'));
define('CurDBName', 'Peers_' . (($curHour === 2 || $curHour === 4 || $curHour === 6 || $curHour === 8 || $curHour === 10 || $curHour === 12) ? '1' : '2'));
define('OldDBName', 'Peers_' . (CurDBName === 'Peers_2' ? '1' : '2'));

// Redis Config
define('CacheAddress', '/var/run/redis/redis.sock');
define('CachePort', 0);
define('CachePersistence', true);
define('CacheAuth', null); // Auth or null.
define('CacheTimeout', 16); // Second.
define('CacheRetryWaitTime', 10);

// Key Config
define('GeneralDebugKey', '221210');
define('AdminKey', 'ak-');
define('UserKeyPrefix', 'uk-');
define('UserKeyDir', 'UserKey-Custom');
// 返回 Debug Level.
function CheckKeyAvailability(string $key): int {
	global $db, $curSimpleTrackerKey;
	if ($key === GeneralDebugKey) {
		$curSimpleTrackerKey = GeneralDebugKey;
		return 1;
	}
	if ($key === AdminKey) {
		$curSimpleTrackerKey = AdminKey;
		return 10;
	}
	if (strpos($key, UserKeyPrefix) === 0) {
		$clientEscapedDebugKey = $db->escape_string($key);
		if (($keyAvailabilityCheckQuery = $db->query("SELECT 1 FROM SimpleTrackerKey WHERE `key` = '{$clientEscapedDebugKey}' AND (expiry_date > CURDATE() OR expiry_date IS NULL) LIMIT 1")) !== false && $keyAvailabilityCheckQuery->num_rows > 0) {
			$curSimpleTrackerKey = $clientEscapedDebugKey;
			return 10;
		}
	}
	$curSimpleTrackerKey = null;
	return 0;
}

// Telegram Config
define('TG_API_URL', 'https://api.telegram.org/botid:botsecret/');
define('TG_GROUP', '@SimpleTrackerGroup');

// Autoclean Config
define('CheckInterval', 2);
define('IndexSleepTime', 500000); // Microsecond, 1 Second = 1000000 Microsecond.
define('IndexSleepTime_Scan', 500000); // Microsecond, 1 Second = 1000000 Microsecond.
define('IndexCount', 10000);
define('NginxPIDFile', '/var/run/nginx.pid'); // Letters or numbers only.
define('NginxAccessLogFile', '/var/log/nginx/access.log'); // Letters or numbers only.
define('CacheTimeout_Autoclean', 900); // Second.

// Announce Config
define('AnnounceInterval', 600);
define('AnnounceMinInterval', 120);
define('AnnounceMaxInterval', ceil(AnnounceInterval * 2)); // Associated with the database table, modification is not recommended.
define('PremiumAnnounceInterval', 300);
define('PremiumAnnounceMinInterval', 60);
define('PremiumAnnounceMaxInterval', ceil(PremiumAnnounceInterval * 2));
define('ScrapeMinInterval', 120);

// Message Config
define('ServerMessage', '服务器 Telegram 群组 (反馈与交流 BitTorrent 相关内容): https://t.me/SimpleTrackerGroup'); // Message or null.
define('ErrorMessage', array(
	1 => '服务器内部错误. (EC: 1)',
	2 => '服务器不喜欢你, 因此你不得不爬. (EC: 2)',
	3 => '服务器认为格式有误. (EC: 3)',
	4 => '服务器认为数量过多. (EC: 4)',
	5 => '服务器认为 Tracker 地址有误. (EC: 5)',
	6 => '服务器已禁止你的客户端, 建议你换用其它客户端. (EC: 6)',
	7 => 'The server has banned your client, it is recommended that you switch to the new version of the client. (EC: 7)',
	8 => '服务器无法验证这个 SimpleTracker Key. (EC: 8)',
	9 => '服务器认为传递的信息不合法. (EC: 9)',
	10 => '服务器发现这个种子在黑名单. (EC: 10)',
	11 => '服务器当前不提供此服务. (EC: 11)'
));
define('WarningMessage', array(
	1 => '服务器无法验证你的端口, 因此你的速度可能会受到影响. (WC: 1)',
	2 => '服务器无法接收到你的上传或下载数据, 因此无法进行统计. (WC: 2)', // 已淘汰
	3 => 'The server has banned your client, it is recommended that you switch to the new version of the client. (WC: 3)',
	4 => '服务器可能会禁止你的客户端, 建议你换用其它客户端. (WC: 4)'
));
?>
