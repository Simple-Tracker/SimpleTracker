<?php
use Swoole\Coroutine\Http\Server;
use function Swoole\Coroutine\run;

require_once('Bencode.php');
require_once('index.php');

run(function () {
	$server = new Server('127.0.0.1', 7701, false);
	$server->handle('/', 'ProcessIndex');
	$server->start();
});
?>
