<?php
use Swoole\Database\MysqliPool;
use Swoole\Database\MysqliConfig;
use Swoole\Coroutine\Http\Server;
use function Swoole\Coroutine\run;

require_once('config.php');
require_once('Bencode.php');
require_once('index.php');
require_once('announce.php');

$infoHashArr = [];

echo "初始化数据库连接池...\n";
$dbConfig = (new MysqliConfig)
	->withHost(DBAddress)
	->withPort(DBPort)
	->withUnixSocket(DBSocket)
	->withDbName(DBName)
	->withCharset('utf8mb4')
	->withUsername(DBUser)
	->withPassword(DBPass);

$dbPool = new MysqliPool($dbConfig, 128);

echo "初始化 HTTP 服务器...\n";
run(function () {
	$server = new Server('127.0.0.1', 7701, false);
	$server->handle('/', 'ProcessIndex');
	$server->handle('/announce', 'ProcessAnnounce');
	$server->handle('/announce.php', 'ProcessAnnounce');
	$server->start();
})
?>
