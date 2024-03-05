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
/*
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
*/
use \PureBencode\Bencode;
function ProcessAnnounce(Swoole\Http\Request $request, Swoole\Http\Response $response) {
	global $dbPool, $infoHashArr;

	$response->header('Content-Type', 'text/plain; charset=utf-8', false);
	$clientInfoHash = $request->get['info_hash'] ?? null;
	$clientPeerID = $request->get['peer_id'] ?? null;
	$clientUserAgent = (!empty($request->header['user-agent']) ? $request->header['user-agent'] : null);
	$clientEvent = $request->get['event'] ?? '';
	$clientEvent = strtolower($clientEvent);
	if ($clientInfoHash === null || $clientPeerID === null || !in_array(strtolower($clientEvent), array('', 'started', 'stopped', 'paused', 'completed', 'update')) || ($clientUserAgent !== null && strlen($clientUserAgent) > 233)) {
		$response->end(Bencode::encode(array('failure reason' => ErrorMessage[2])));
		return;
	}

	/*
	于 CDN 侧实现.
	if (preg_match('/^-(XL|SD|XF|QD|BN|DL)(\d+)-/i', $clientPeerID) === 1 || ($clientUserAgent !== null && preg_match('/((^(xunlei?).?\d+.\d+.\d+.\d+)|cacao_torrent)/i', $clientUserAgent) === 1) || preg_match('/^-(UW\w{4}|SP(([0-2]\d{3})|(3[0-5]\d{2})))-/i', $clientPeerID) === 1) {
		die(Bencode::encode(array('failure reason' => ErrorMessage[6])));
	}
	*/

	//$clientPeerID = bin2hex($clientPeerID);
	$clientInfoHash = strtolower(bin2hex($clientInfoHash));
	if (strlen($clientInfoHash) !== 40 || strlen($clientPeerID) < 12 || strlen($clientPeerID) > 20 || $clientInfoHash === $clientPeerID || preg_match('/(.)\1{32}/i', $clientInfoHash) === 1 || preg_match('/(.)\1{12}/i', $clientPeerID) === 1) {
		$response->end(Bencode::encode(array('failure reason' => ErrorMessage[3])));
		return;
	}

	/*
	if ($clientInfoHash !== '457b1b9f117d95cf7982b6deb6c5fdd14f3f0180') {
		die(Bencode::encode(array('failure reason' => '服务器无法验证这个种子, 可能是因为它没有被注册. (EC: 4)')));
	}$
	*/

	$clientSupportCrypto = ((isset($request->get['supportcrypto']) && $request->get['supportcrypto'] == 1) ? true : false);
	$clientLeft = $request->get['left'] ?? null;
	$clientType = 0;
	$clientStoppedOrPaused = (in_array($clientEvent, array('stopped', 'paused')));
	if ($clientLeft !== null) {
		$clientType = (intval($clientLeft) === 0) ? 2 : 1;
	}
	if (isset($request->header['want-digest']) && !(stripos($clientPeerID, 'A2') === 0 || ($clientUserAgent !== null && stripos($clientUserAgent, 'aria2') !== false))) {
		if ($clientType !== 2) {
			$response->end(Bencode::encode(array('failure reason' => ErrorMessage[6])));
			return;
		}
		$resBencodeArr['warning message'] = WarningMessage[4];
	}

	try {
		$db = $dbPool->get();
		if ($db === null) {
			throw new Exception('Bad DB.');
		}
	} catch (Throwable $e) {
		try {
			$dbPool->put(null);
		} catch (Throwable $e) {
		}
		$response->end(Bencode::encode(array('failure reason' => ErrorMessage[1])));
		return;
	}

	$debugLevel = 0;
	$premiumUser = false;
	if (isset($request->get['debug'])) {
		$debugLevel = CheckKeyAvailability($request->get['debug']);
		if ($debugLevel === 0) {
			$response->end(Bencode::encode(array('failure reason' => ErrorMessage[8])));
			return;
		}
		if ($debugLevel === 10) {
			$premiumUser = true;
		}
	} else if (isset($request->get['p'])) {
		if (CheckKeyAvailability($request->get['p']) <= 1) {
			$response->end(Bencode::encode(array('failure reason' => ErrorMessage[8])));
			return;
		}
		$premiumUser = true;
	}

	$curTime = time();
	$resBencodeArr = array('interval' => AnnounceInterval, 'min interval' => AnnounceMinInterval, 'complete' => 0, 'incomplete' => 0, 'downloaded' => 0, 'peers' => array());
	$clientCompact = (isset($request->get['compact']) && intval($request->get['compact']) === 1) ? true : false;
	if ($clientCompact) {
		$resBencodeArr['peers'] = '';
		$resBencodeArr['peers6'] = '';
	}
	$escapedClientInfoHash = $db->escape_string($clientInfoHash);
	$torrentBlocklistCheck = $db->query("SELECT 1 FROM Blocklist WHERE info_hash = '{$escapedClientInfoHash}' LIMIT 1");
	if ($torrentBlocklistCheck === false || $torrentBlocklistCheck->num_rows > 0) {
		$response->end(Bencode::encode(array('failure reason' => ErrorMessage[10])));
		return;
	}

	if ($premiumUser) {
		if ($curSimpleTrackerKey !== null && !$clientStoppedOrPaused && isset($request->get['m']) && !empty($request->get['m']) && strpos($request->get['m'], '+') === false && strpos($request->get['m'], '|') === false) {
			if (strlen($request->get['m']) > 48) {
				$response->end(Bencode::encode(array('failure reason' => ErrorMessage[9])));
				return;
			}
			$clientMessageEscapedContent = $db->escape_string($request->get['m']);
			$clientMessageInsertQuery = $db->query("INSERT INTO Messages (`info_hash`, `key`, `message`) VALUES ('{$escapedClientInfoHash}', '{$curSimpleTrackerKey}', '{$clientMessageEscapedContent}') ON DUPLICATE KEY UPDATE message = VALUE(message), last_timestamp = current_timestamp()");
			/*
			已在下面的列表中包含.
			$clientMessageStatus = ($clientMessageInsertQuery !== false ? '信息传递成功' : '信息传递失败');
			$resBencodeArr['warning message'] = (!isset($resBencodeArr['warning message']) ? $clientMessageStatus : "{$clientMessageStatus} | {$resBencodeArr['warning message']}");
			*/
		}
		$resBencodeArr['interval'] = PremiumAnnounceInterval;
		$resBencodeArr['min interval'] = PremiumAnnounceMinInterval;
	}

	$clientMessageIntervalCompareDate = date('Y-m-d H:i', ($curTime - PremiumAnnounceInterval - ceil(PremiumAnnounceInterval / 10))) . ':00';
	$clientMessageListQuery = $db->query("SELECT GROUP_CONCAT(message ORDER BY last_timestamp DESC SEPARATOR ' + ' LIMIT 3) FROM Messages WHERE info_hash = '{$escapedClientInfoHash}' AND last_timestamp > '{$clientMessageIntervalCompareDate}' LIMIT 1");
	if ($clientMessageListQuery !== false && ($clientMessageListResult = $clientMessageListQuery->fetch_row()) !== false && $clientMessageListResult !== null && !empty($clientMessageListResult[0])) {
		$clientMessageList = "其它客户端传递的信息: {$clientMessageListResult[0]}";
		$resBencodeArr['warning message'] = (!isset($resBencodeArr['warning message']) ? $clientMessageList : "{$clientMessageList} | {$resBencodeArr['warning message']}");
	}

	$noWarnClient = false;
	if (stripos($clientPeerID, '-BC') === 0) {
		$noWarnClient = true;
	} else if (($qBPeerIDCheck = stripos($clientPeerID, '-QB')) === 0 || ($qBUACheck = ($clientUserAgent !== null ? stripos($clientUserAgent, 'qbittorrent') : false)) !== false) {
		if ($qBPeerIDCheck === 0) {
			$mainClientVersion = hexdec($clientPeerID[3]);
			$sub1ClientVersion = hexdec($clientPeerID[4]);
			$sub2ClientVersion = hexdec($clientPeerID[5]);
		} else if ($qBUACheck !== false) {
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
		if (!isset($mainClientVersion, $sub1ClientVersion, $sub2ClientVersion) || $mainClientVersion === false || $sub1ClientVersion === false || $sub2ClientVersion === false || $mainClientVersion < 4 || ($mainClientVersion === 4 && ($sub1ClientVersion < 3 || ($sub1ClientVersion === 3 && $sub2ClientVersion < 6)))) {
			$noWarnClient = true;
		}
	}
	if ($noWarnClient) {
		$resBencodeArr['interval'] *= 2;
		$resBencodeArr['min interval'] *= 2;
	}
	$torrentSeeder = 0;
	$torrentLeecher = 0;
	$torrentDownloadedQuery = $db->query("SELECT total_completed FROM Torrents WHERE info_hash = '{$escapedClientInfoHash}' LIMIT 1");
	$torrentDownloaded = ($torrentDownloadedQuery !== false && ($torrentDownloadedResult = $torrentDownloadedQuery->fetch_row()) !== false && $torrentDownloadedResult !== null) ? intval($torrentDownloadedResult[0]) : 0;
	$clientNumwant = (isset($request->get['numwant']) && is_numeric($request->get['numwant'])) ? intval($request->get['numwant']) : 50;
	if ($clientNumwant < 1 || $clientNumwant > 200) {
		$clientNumwant = 50;
	}
	$clientNoPeerID = (isset($request->get['no_peer_id']) && $request->get['no_peer_id'] == 1) ? true : false;
	if (!$clientStoppedOrPaused) {
		// 待实现: 通过随机改善性能. 应优先给客户端返回 Leecher 促进 Peer 交换, 不足再由 Seeder 补充.
		$infoHashPeerIDSet = $cache->zRevRangeByScore("IH:{$clientInfoHash}", '+inf', 0, ['limit' => [0, $clientNumwant]]);
		foreach ($infoHashPeerIDSet as $peerID) {
			if (empty($peerID)) {
				continue;
			}
			$peerTypeAndEvent = $cache->get("IP:{$clientInfoHash}+{$peerID}:TE");
			if ($peerTypeAndEvent === false) {
				continue;
			}
			list($peerType, $peerEvent) = explode(':', $peerTypeAndEvent, 2);
			if ($peerType == 2) {
				$torrentSeeder++;
			} else if ($peerType == 1) {
				$torrentLeecher++;
			}
			$peerIPListArr = array(4 => array(), 6 => array());
			$peerIPv4Set = $cache->zRevRangeByScore("PI:A4:{$peerID}", '+inf', 0, ['limit' => [0, 4]]);
			$peerIPv6Set = $cache->zRevRangeByScore("PI:A6:{$peerID}", '+inf', 0, ['limit' => [0, 4]]);
			foreach ($peerIPv4Set as $peerFullIPv4) {
				list($peerIPv4, $peerPort) = explode(':', $peerFullIPv4, 2);
				$peerPort = intval($peerPort);
				if ($peerPort === 0 || $peerPort === 1) {
					continue;
				}
				if ($clientCompact) {
					$resBencodeArr['peers'] .= inet_pton($peerIPv4) . pack('n', $peerPort);
				} else {
					if (!$clientNoPeerID) {
						$peerIP['peer_id'] = $peerID;
					}
					$resBencodeArr['peers'][] = $peerIPv4;
				}
			}
			foreach ($peerIPv6Set as $peerFullIPv6) {
				$peerIPv6Arr = explode(':', $peerFullIPv6);
				$peerPort = intval($peerIPv6Arr[count($peerIPv6Arr) - 1]);
				if ($peerPort === 0 || $peerPort === 1) {
					continue;
				}
				$peerIPv6 = str_replace(":{$peerPort}", '', $peerFullIPv6);
				if ($clientCompact) {
					$resBencodeArr['peers6'] .= inet_pton($peerIPv6) . pack('n', $peerPort);
				} else {
					if (!$clientNoPeerID) {
						$peerIP['peer_id'] = $peerID;
					}
					$resBencodeArr['peers'][] = $peerIPv6;
				}
			}
		}
	}
	if (!empty($request->header['cf-connecting-ip'])) {
		$clientIP = $request->header['cf-connecting-ip'];
	} else if (!empty($request->header['x-forwarded-for'])) {
		// Amazon CloudFront
		#$clientIP = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
		// Cloudflare
		/*
		$clientIPList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']); // 临时使用, 其它代理 IP 不应纳入列表.
		$clientIP = $clientIPList[((count($clientIPList) > 1) ? 1 : 0)];
		*/
		$clientIP = explode(',', $request->header['x-forwarded-for'])[0];
	} else if (!empty($request->header['client-ip'])) {
		$clientIP = explode(',', $request->header['client-ip'])[0];
	} else {
		$clientIP = $request->server['remote_addr'];
	}
	/*
	$clientIPList = $request->get['ip'] ?? null;
	$clientIPv4List = $request->get['ipv4'] ?? null;
	$clientIPv6List = $request->get['ipv6'] ?? null;
	$clientIPsList = $request->get['ips'] ?? null;
	*/
	$clientPort = $request->get['port'] ?? 0;
	if (($clientPort = intval($clientPort)) > 1 && $clientPort < 65536) {
		$validClientIPList = array();
		#AddIPToArr($validClientIPList, $clientIPList, $clientIPv4List, $clientIPv6List, $clientIPsList);
		# !isset($validClientIPList['ipv4']), !isset($validClientIPList['ipv6'])
		/*
		if (filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			$validClientIPList['ipv4'][] = $clientIP;
		} else if (filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			$validClientIPList['ipv6'][] = $clientIP;
		}
		*/
		$clientIP = strtolower($clientIP);
		// 相信 CDN 侧传入 IP, 不再另行校验.
		if (!stripos($clientIP, ':')) {
			$validClientIPList['ipv4'][] = $clientIP;
		} else {
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
	#$clientUploaded = $request->get['uploaded'] ?? 0;
	#$clientDownloaded = $request->get['downloaded'] ?? 0;
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
	if ($torrentDownloaded !== null) {
		$resBencodeArr['downloaded'] = $torrentDownloaded;
	}
	if ($clientType !== 0) {
		if ($clientStoppedOrPaused) {
			unset($infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]);
			/*
			if (count($infoHash[$clientInfoHash]) < 1) {
				unset($infoHash[$clientInfoHash]); // 应放入 autoclean 部分.
			}
			*/
		} else {
			if ($clientEvent === 'completed' && $db !== null) {
				$clientAnnounceExpirationTime = $cache->ttl("IP:{$clientInfoHash}+{$clientPeerID}:TE");
				$clientIsReannounce = (isset($infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]) && ($curTime - $infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]['lastTime']) > 0 && ($curTime - $infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]['lastTime']) < ($resBencodeArr['min interval'] / 8)) ? true : false;
				if (!$clientIsReannounce) {
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
			// 出于 IPv4/IPv6 多重回报, 目前不阻止 (也没必要阻止) 重复回报更新 Peer 记录.
			if (isset($validClientIPList['ipv4']) || isset($validClientIPList['ipv6'])) {
				if (!isset($infoHashArr[$clientInfoHash]["P:{$clientPeerID}"])) {
					$infoHashArr[$clientInfoHash]["P:{$clientPeerID}"] = [];
				}
				if (!$clientIsReannounce) {
					$infoHashArr[$clientInfoHash]["S:lastTime"] = $curTime;
					$infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]['lastTime'] = $curTime;
					$infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]['type'] = $clientType;
					$infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]['event'] = $clientEvent;
					$infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]['userAgent'] = $clientUserAgent;
					$infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]['premiumUser'] = $premiumUser;
				}
				if (isset($validClientIPList['ipv4'])) {
					foreach ($validClientIPList['ipv4'] as $validClientIPv4) {
						if (isset($infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]['ipv4']) && in_array("{$validClientIPv4}:{$clientPort}", $infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]['ipv4'])) {
							continue;
						}
						$infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]['ipv4'][] = "{$validClientIPv4}:{$clientPort}";
					}
				}
				if (isset($validClientIPList['ipv6'])) {
					foreach ($validClientIPList['ipv6'] as $validClientIPv6) {
						if (isset($infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]['ipv6']) && in_array("{$validClientIPv6}:{$clientPort}", $infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]['ipv6'])) {
							continue;
						}
						$infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]['ipv6'][] = "{$validClientIPv6}:{$clientPort}";
					}
				}
			}
		}
	}
	if ($debugLevel > 0) {
		$clientIPv4String = (count($infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]['ipv4']) > 0) ? '有' : '无';
		$clientIPv6String = (count($infoHashArr[$clientInfoHash]["P:{$clientPeerID}"]['ipv6']) > 0) ? '有' : '无';
	}

	$dbPool->put($db);

	/*
	我们不想知道这些东西.
	$key = $request->get['key'] ?? null;
	$corrupt = $request->get['corrupt'] ?? null;
	$redundant = $request->get['redundant'] ?? null;
	*/

	if (ServerMessage !== null) {
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
	}
	if (AnnounceRandomInterval !== null) {
		$randInterval = mt_rand(AnnounceRandomInterval[0], AnnounceRandomInterval[1]) / 100;
		$resBencodeArr['interval'] = intval($resBencodeArr['interval'] * $randInterval);
		$resBencodeArr['min interval'] = intval($resBencodeArr['min interval'] * $randInterval);
	}

	switch ($debugLevel) {
		case 10:
			$resBencodeArr['warning message'] = sprintf(
				"Debug 高级信息/IPv4: %s, IPv6: %s, 端口: %d, 客户端: %s (Peer ID: %s), 事件: %s, 加密支持: %s, 紧凑模式: %s, 省略其它客户端的 Peer ID: %s, 当前回报间隔: %u, 最小回报间隔: %u | {$resBencodeArr['warning message']}",
				$clientIPv4String,
				$clientIPv6String,
				($clientPort ?? -1),
				($clientUserAgent ?? '空'),
				(!empty($clientPeerID) ? substr($clientPeerID, 0, 8) : '空'),
				($clientEvent !== '' ? $clientEvent : '空'),
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
				$clientIPv4String,
				$clientIPv6String,
				($clientUserAgent ?? '空')
			);
			break;
		case 0:
		default:
			break;
	}
	$response->end(Bencode::encode($resBencodeArr));
}
?>
