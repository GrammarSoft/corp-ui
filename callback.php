<?php

declare(strict_types=1);
require_once __DIR__.'/_inc/lib.php';

header('HTTP/1.1 200 Ok');
$a = !empty($_REQUEST['a']) ? $_REQUEST['a'] : '';
$rv = [
	'a' => $a,
	];

$origin = '*';
if (!empty($_SERVER['HTTP_ORIGIN'])) {
	$origin = trim($_SERVER['HTTP_ORIGIN']);
}
header('Access-Control-Allow-Origin: '.$origin);
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	header('HTTP/1.1 200 Options');
	die();
}

while ($a === 'load') {
	if (empty($_REQUEST['h']) || !preg_match('~^[a-z0-9]{20}$~', $_REQUEST['h'])) {
		$rv['errors'][] = 'E010: Invalid hash '.$_REQUEST['h'];
		break;
	}
	$hash = $_REQUEST['h'];
	$rv['h'] = $hash;
	$folder = '/home/manatee/cache/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2);
	if (!is_dir($folder)) {
		$rv['errors'][] = 'E020: Invalid hash '.$_REQUEST['h'];
		break;
	}
	chdir($folder);

	$context = min(max(intval($_REQUEST['c'] ?? 7), 0), 15);
	$rv['c'] = $context;

	$corps = [];
	$rs = [];
	$ts = [];
	$cs = [];
	if (!empty($_REQUEST['rs']) && is_array($_REQUEST['rs'])) {
		$rs = filter_corpora_k($_REQUEST['rs']);
		$corps = array_merge($corps, array_keys($rs));
	}
	if (!empty($_REQUEST['ts']) && is_array($_REQUEST['ts'])) {
		$ts = filter_corpora_v($_REQUEST['ts']);
		$corps = array_merge($corps, $ts);
	}
	if (!empty($_REQUEST['cs']) && is_array($_REQUEST['cs'])) {
		$cs = filter_corpora_k($_REQUEST['cs']);
		$corps = array_merge($corps, array_keys($cs));
	}
	sort($corps);
	$corps = array_unique($corps);

	clearstatcache();
	$dbs = [];
	foreach ($corps as $corp) {
		if (!file_exists("$hash-$corp.sqlite") || !filesize("$hash-$corp.sqlite")) {
			$rv['info'][] = 'Not ready '.$corp;
			continue;
		}
		$dbs[$corp] = new \TDC\PDO\SQLite("$hash-$corp.sqlite", [\PDO::SQLITE_ATTR_OPEN_FLAGS => \PDO::SQLITE_OPEN_READONLY]);
	}

	foreach ($cs as $corp => $c) {
		$cs[$corp]['rs'] = [];
		foreach (explode(',', $c['rs']) as $i) {
			$cs[$corp]['rs'][$i] = 0;
		}
	}

	foreach ($rs as $corp => $r) {
		if (!array_key_exists($corp, $dbs)) {
			$rv['info'][] = 'I010: Not loaded '.$corp;
			continue;
		}

		$hits = $dbs[$corp]->prepexec("SELECT hit_id as i, hit_pos as p, hit_text as t FROM hits WHERE hit_id >= ? AND hit_id < ? ORDER BY hit_id ASC", [intval($r['s']), intval($r['s'])+intval($r['n'])])->fetchAll();
		$rv['rs'][$corp] = [
			's' => intval($r['s']),
			'n' => intval($r['n']),
			'es' => $hits,
			];

		foreach ($hits as $hit) {
			$cs[$corp]['rs'][$hit['i']] = $hit['p'];
		}
	}

	foreach ($ts as $corp) {
		if (!array_key_exists($corp, $dbs)) {
			$rv['info'][] = 'I020: Not loaded '.$corp;
			continue;
		}

		$cnt = [
			'n' => intval($dbs[$corp]->prepexec("SELECT max(hit_id) as cnt FROM hits")->fetchAll()[0]['cnt'] ?? 0),
			'd' => 1,
			];
		if (!file_exists("$hash-$corp.time") || !filesize("$hash-$corp.time")) {
			$cnt['d'] = 0;
		}
		$rv['ts'][$corp] = $cnt;
	}

	foreach ($cs as $corp => $c) {
		if (!array_key_exists($corp, $dbs)) {
			$rv['info'][] = 'I030: Not loaded '.$corp;
			continue;
		}

		if (file_exists("$hash-$corp.$context.sqlite") && filesize("$hash-$corp.$context.sqlite")) {
			$ids = array_keys($c['rs']);
			$db = new \TDC\PDO\SQLite("$hash-$corp.$context.sqlite", [\PDO::SQLITE_ATTR_OPEN_FLAGS => \PDO::SQLITE_OPEN_READONLY]);
			$exist = $db->prepexec("SELECT hit_id as i, c_begin as b, c_end as e, c_text as t FROM contexts WHERE hit_id IN (?".str_repeat(', ?', count($ids)-1).")", $ids)->fetchAll();
			foreach ($exist as $e) {
				unset($c['rs'][$e['i']]);
			}
			$rv['cs'][$corp] = $exist;
			unset($db);
			//header('X-Context-Todo: '.count($rngs));
		}
		else {
			$db = new \TDC\PDO\SQLite("$hash-$corp.$context.sqlite");
			$db->exec("PRAGMA journal_mode = delete");
			$db->exec("PRAGMA page_size = 65536");
			$db->exec("VACUUM");

			$db->exec("PRAGMA auto_vacuum = INCREMENTAL");
			$db->exec("PRAGMA case_sensitive_like = ON");
			$db->exec("PRAGMA foreign_keys = OFF");
			$db->exec("PRAGMA ignore_check_constraints = OFF");
			$db->exec("PRAGMA journal_mode = WAL");
			$db->exec("PRAGMA locking_mode = NORMAL");
			$db->exec("PRAGMA synchronous = OFF");
			$db->exec("PRAGMA threads = 4");
			$db->exec("PRAGMA trusted_schema = OFF");

			$db->exec("CREATE TABLE meta (
				key TEXT NOT NULL,
				value TEXT NOT NULL,
				PRIMARY KEY (key)
			)");
			$db->exec("CREATE TABLE contexts (
				hit_id INTEGER NOT NULL,
				c_begin INTEGER NOT NULL,
				c_end INTEGER NOT NULL,
				c_text TEXT NOT NULL,
				PRIMARY KEY (hit_id)
			) WITHOUT ROWID");
			unset($db);
		}

		if (!empty($c['rs'])) {
			$ids = array_keys($c['rs']);
			$hits = $dbs[$corp]->prepexec("SELECT hit_id as i, hit_pos as p, hit_text as t FROM hits WHERE hit_id IN (?".str_repeat(', ?', count($ids)-1).")", $ids)->fetchAll();
			if (!empty($hits)) {
				$rngs = [];
				foreach ($hits as $hit) {
					$rngs[] = max($hit['p']-$context, 0).','.($hit['p']+substr_count($hit['t'], ' ')+$context+1).','.$hit['i'];
				}

				if (!empty($rngs)) {
					$rngs = escapeshellarg(implode(';', $rngs));
					//header('X-Context-Doing: '.$rngs);
					$hash_rn = substr(sha256_lc20($rngs), 0, 8);
					shell_exec("nice -n10 /usr/bin/time -f '%e' -o $hash-$corp.$context-$hash_rn.time /home/manatee/public_html/_bin/decodevert-ranges /home/manatee/registry/$corp $rngs | /home/manatee/public_html/_bin/context2sqlite $hash-$corp.$context.sqlite >$hash-$corp.$context-$hash_rn.err 2>&1 &");
				}
			}
		}
	}

	break;
}

if (!empty($rv['errors'])) {
	header('HTTP/1.1 400 Bad Request');
}

echo json_encode_vb($rv);

/*
// …

	$rngs = escapeshellarg(implode('; ', $rngs));
	//echo '<tr><td colspan="4">'.$GLOBALS['CORP_ROOT'].'/_bin/decodevert-ranges /home/manatee/registry/'.$corp.' '.$rngs.'</td></tr>';
	echo '</tbody></table></div>';
*/
