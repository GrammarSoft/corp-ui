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

while ($a === 'conc') {
	if (empty($_REQUEST['h']) || !preg_match('~^[a-z0-9]{20}$~', $_REQUEST['h'])) {
		$rv['errors'][] = 'E010: Invalid hash '.$_REQUEST['h'];
		break;
	}
	$hash = $_REQUEST['h'];
	$folder = $GLOBALS['CORP_ROOT'].'/cache/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2);
	if (!is_dir($folder)) {
		$rv['errors'][] = 'E020: Invalid hash '.$_REQUEST['h'];
		break;
	}
	chdir($folder);

	$context = min(max(intval($_REQUEST['c'] ?? 7), 0), 15);
	$rv['c'] = $context;
	$offset = max(intval($_REQUEST['s'] ?? 0), 0);
	$rv['s'] = $offset;
	$pagesize = min(max(intval($_REQUEST['n'] ?? 50), 50), 500);
	$rv['n'] = $pagesize;

	$corps = [];
	$rs = [];
	$ts = [];
	$cs = [];
	if (!empty($_REQUEST['rs']) && is_array($_REQUEST['rs'])) {
		$rs = filter_corpora_v($_REQUEST['rs']);
		$corps = array_merge($corps, $ts);
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

	foreach ($rs as $corp) {
		if (!array_key_exists($corp, $dbs)) {
			$rv['info'][] = 'I010: Not loaded '.$corp;
			continue;
		}

		$hits = $dbs[$corp]->prepexec("SELECT hit_id as i, hit_pos as p, hit_text as t FROM hits WHERE hit_id >= ? AND hit_id < ? ORDER BY hit_id ASC", [$offset, $offset+$pagesize])->fetchAll();
		$rv['rs'][$corp] = $hits;

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
					[$s_corp,$subc] = explode('-', $corp.'-');
					$rngs = escapeshellarg(implode(';', $rngs));
					//header('X-Context-Doing: '.$rngs);
					$hash_rn = substr(sha256_lc20($rngs), 0, 8);
					shell_exec("nice -n10 /usr/bin/time -f '%e' -o $hash-$corp.$context-$hash_rn.time '{$GLOBALS['WEB_ROOT']}/_bin/decodevert-ranges' '{$GLOBALS['CORP_ROOT']}/registry/$s_corp' $rngs | '{$GLOBALS['WEB_ROOT']}/_bin/context2sqlite' $hash-$corp.$context.sqlite >$hash-$corp.$context-$hash_rn.err 2>&1 &");
				}
			}
		}
	}

	break;
}

while ($a === 'freq') {
	if (empty($_REQUEST['h']) || !preg_match('~^[a-z0-9]{20}$~', $_REQUEST['h'])) {
		$rv['errors'][] = 'E010: Invalid hash '.$_REQUEST['h'];
		break;
	}
	if (empty($_REQUEST['hf']) || !preg_match('~^[a-z0-9]{8}$~', $_REQUEST['hf'])) {
		$rv['errors'][] = 'E040: Invalid frequency hash '.$_REQUEST['hf'];
		break;
	}
	if (empty($_REQUEST['t']) || !preg_match('~^(abc|freq|relg|relc|rels)$~', $_REQUEST['t'])) {
		$rv['errors'][] = 'E030: Invalid type '.$_REQUEST['t'];
		break;
	}

	$hash = $_REQUEST['h'];
	$hash_freq = $_REQUEST['hf'];
	$folder = $GLOBALS['CORP_ROOT'].'/cache/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2);
	if (!is_dir($folder)) {
		$rv['errors'][] = 'E020: Invalid hash '.$_REQUEST['h'];
		break;
	}
	chdir($folder);

	$type = $_REQUEST['t'];
	$offset = max(intval($_REQUEST['s'] ?? 0), 0);
	$rv['s'] = $offset;
	$pagesize = min(max(intval($_REQUEST['n'] ?? 50), 50), 500);
	$rv['n'] = $pagesize;

	$corps = [];
	$cs = [];
	if (!empty($_REQUEST['cs']) && is_array($_REQUEST['cs'])) {
		$cs = filter_corpora_v($_REQUEST['cs']);
		$corps = array_merge($corps, $cs);
	}
	sort($corps);
	$corps = array_unique($corps);

	clearstatcache();
	$dbs = [];
	foreach ($corps as $corp) {
		if (!file_exists("$hash-$corp.freq-$hash_freq.sqlite") || !filesize("$hash-$corp.freq-$hash_freq.sqlite")) {
			$rv['info'][] = 'Not ready '.$corp;
			continue;
		}
		[$s_corp,$subc] = explode('-', $corp.'-');
		$dbs[$corp] = [
			'freq' => new \TDC\PDO\SQLite("$hash-$corp.freq-$hash_freq.sqlite", [\PDO::SQLITE_ATTR_OPEN_FLAGS => \PDO::SQLITE_OPEN_READONLY]),
			'corp' => new \TDC\PDO\SQLite("{$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/meta/stats.sqlite", [\PDO::SQLITE_ATTR_OPEN_FLAGS => \PDO::SQLITE_OPEN_READONLY]),
			];
	}

	foreach ($cs as $corp) {
		$rv['cs'][$corp] = [
			'n' => 0,
			't' => 0,
			'w' => 0,
			'ws' => 0,
			'd' => 1,
			'f' => [],
			];

		if (!array_key_exists($corp, $dbs)) {
			$rv['cs'][$corp]['d'] = 0;
			$rv['info'][] = 'I010: Not loaded '.$corp;
			continue;
		}

		[$s_corp,$subc] = explode('-', $corp.'-');
		if (!file_exists("$hash-$corp.freq-$hash_freq.time") || !filesize("$hash-$corp.freq-$hash_freq.time")) {
			$rv['cs'][$corp]['d'] = 0;
		}

		$rv['cs'][$corp]['n'] = intval($dbs[$corp]['freq']->prepexec("SELECT MAX(rowid) as cnt FROM freqs")->fetchAll()[0]['cnt'] ?? 0);
		$rv['cs'][$corp]['t'] = intval($dbs[$corp]['freq']->prepexec("SELECT value FROM meta WHERE key = 'total'")->fetchAll()[0]['value'] ?? 1);
		$rv['cs'][$corp]['w'] = intval($dbs[$corp]['corp']->prepexec("SELECT c_words+c_numbers+c_alnums as cnt FROM counts WHERE c_which = 'total'")->fetchAll()[0]['cnt'] ?? 1);
		if (!empty($subc)) {
			$rv['cs'][$corp]['ws'] = intval($dbs[$corp]['corp']->prepexec("SELECT c_words+c_numbers+c_alnums as cnt FROM counts WHERE c_which = ?", [$subc])->fetchAll()[0]['cnt'] ?? 1);
		}

		$res = null;
		if ($type === 'abc') {
			$res = $dbs[$corp]['freq']->prepexec("SELECT f_text, f_abs, f_rel_g, f_rel_c, f_rel_s FROM freqs ORDER BY f_text ASC, f_abs DESC LIMIT {$pagesize} OFFSET {$offset}-1");
		}
		else if ($type === 'relg') {
			$res = $dbs[$corp]['freq']->prepexec("SELECT f_text, f_abs, f_rel_g, f_rel_c, f_rel_s FROM freqs ORDER BY f_rel_g DESC, f_abs DESC, f_text ASC LIMIT {$pagesize} OFFSET {$offset}-1");
		}
		else if ($type === 'relc') {
			$res = $dbs[$corp]['freq']->prepexec("SELECT f_text, f_abs, f_rel_g, f_rel_c, f_rel_s FROM freqs ORDER BY f_rel_c DESC, f_abs DESC, f_text ASC LIMIT {$pagesize} OFFSET {$offset}-1");
		}
		else if ($type === 'rels') {
			$res = $dbs[$corp]['freq']->prepexec("SELECT f_text, f_abs, f_rel_g, f_rel_c, f_rel_s FROM freqs ORDER BY f_rel_s DESC, f_abs DESC, f_text ASC LIMIT {$pagesize} OFFSET {$offset}-1");
		}
		else /* if ($type === 'freq') */ {
			$res = $dbs[$corp]['freq']->prepexec("SELECT f_text, f_abs, f_rel_g, f_rel_c, f_rel_s FROM freqs ORDER BY f_abs DESC, f_text ASC LIMIT {$pagesize} OFFSET {$offset}-1");
		}
		while ($row = $res->fetch()) {
			$rv['cs'][$corp]['f'][] = [$row['f_text'], intval($row['f_abs']), floatval($row['f_rel_g']), floatval($row['f_rel_c']), floatval($row['f_rel_s'])];
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
	//echo '<tr><td colspan="4">'.$GLOBALS['WEB_ROOT'].'/_bin/decodevert-ranges '.$GLOBALS['CORP_ROOT'].'/registry/'.$corp.' '.$rngs.'</td></tr>';
	echo '</tbody></table></div>';
*/
