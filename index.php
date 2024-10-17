<?php
declare(strict_types=1);
require_once __DIR__.'/_inc/lib.php';

$_REQUEST['c'] = filter_corpora_k($_REQUEST['c'] ?? []);
$_REQUEST['q'] = trim(preg_replace('~[\r\n\t\s\pZ]+~su', ' ', $_REQUEST['q'] ?? ''));
$_REQUEST['q2'] = trim(preg_replace('~[\r\n\t\s\pZ]+~su', ' ', $_REQUEST['q2'] ?? ''));

$locked = [];
foreach ($_REQUEST['c'] as $corp => $_) {
	$sub = explode('-', $corp.'-');
	$_SESSION['corpora'][$sub[0]] = true;
	if ($GLOBALS['-corplist'][$sub[0]]['locked']) {
		$locked[$sub[0]] = $sub[0];
		$_SESSION['corpora'][$sub[0]] = false;
	}
}
if ($locked) {
	$GLOBALS['-auth']->init();
	foreach ($locked as $corp) {
		if ($GLOBALS['-auth']->check($corp)) {
			unset($locked[$corp]);
		}
	}
}

$h_language = htmlspecialchars(trim($_REQUEST['l'] ?? ''));

$_REQUEST['f'] = trim($_REQUEST['f'] ?? 'word');
if (!array_key_exists($_REQUEST['f'], $GLOBALS['-fields'])) {
	$_REQUEST['f'] = 'word';
}

$_REQUEST['br_f'] = trim($_REQUEST['br_f'] ?? 'word');
if (!array_key_exists($_REQUEST['br_f'], $GLOBALS['-fields'])) {
	$_REQUEST['br_f'] = 'word';
}

$_REQUEST['b'] = trim($_REQUEST['b'] ?? 'le');
$_REQUEST['o'] = max(min(intval($_REQUEST['o'] ?? 0), 4), -4);
$_REQUEST['ga'] = trim($_REQUEST['ga'] ?? 's');
$_REQUEST['cut'] = intval($_REQUEST['cut'] ?? 0);
$_REQUEST['ub'] = $_REQUEST['ub'] ?? '';

$_REQUEST['br_q'] = trim($_REQUEST['br_q'] ?? '');
$h_br_q = htmlspecialchars($_REQUEST['br_q']);

$toasts = [];

$h_query = '';
$checked = [
	'dc' => '',
	'dt' => '',
	'lc' => '',
	'nd' => '',
	'hf' => '',
	'pos' => '',
	'wv' => '',
	'br' => '',
	'xe' => '',
	'xs' => '',
	];

$fields = '';
$freq_fields = '';
$br_freq_fields = '';
foreach ($GLOBALS['-fields'] as $k => $v) {
	$sel = '';
	if ($k === $_REQUEST['f']) {
		$sel = ' selected';
	}
	if (substr($k, 0, 2) !== 'h_') {
		$fields .= '<option value="'.$k.'"'.$sel.'>'.htmlspecialchars($v).'</option>'."\n";
	}
	$freq_fields .= '<option value="'.$k.'"'.$sel.'>'.htmlspecialchars($v).'</option>'."\n";

	$sel = '';
	if ($k === $_REQUEST['br_f']) {
		$sel = ' selected';
	}
	$br_freq_fields .= '<option value="'.$k.'"'.$sel.'>'.htmlspecialchars($v).'</option>'."\n";
}

if (!empty($_REQUEST['dt'])) {
	$checked['dt'] = 'checked';
}
if (!empty($_REQUEST['dc'])) {
	$checked['dc'] = 'checked';
}
if (!empty($_REQUEST['hf'])) {
	$checked['hf'] = 'checked';
}
if (!empty($_REQUEST['pos'])) {
	$checked['pos'] = 'checked';
}
if (!empty($_REQUEST['wv'])) {
	$checked['wv'] = 'checked';
}
if (!empty($_REQUEST['br'])) {
	$checked['br'] = 'checked';
}
if (!empty($_REQUEST['xe'])) {
	$checked['xe'] = 'checked';
}
if (!empty($_REQUEST['xs'])) {
	$checked['xs'] = 'checked';
}

$arr_query = explode('~|~', $_REQUEST['q']);
$arr_query2 = explode('~|~', $_REQUEST['q2']);
header('X-Q: '.count($arr_query));

foreach ($arr_query as $hk => $query) {
	if (empty($_REQUEST['vt']) && !empty($query) && !preg_match('~\[.*\]~', $query)) {
		$qs = explode(' ', $query);
		if (!empty($_REQUEST['nd'])) {
			$query = '[word_nd="'.implode('"] [word_nd="', $qs).'"]';
		}
		else if (!empty($_REQUEST['lc'])) {
			$query = '[word_lc="'.implode('"] [word_lc="', $qs).'"]';
		}
		else {
			$query = '[word="'.implode('"] [word="', $qs).'"]';
		}
	}
	if (preg_match('~\b(\d+):\[.*?\1\.~', $query)) {
		$_REQUEST['ub'] = 2;
	}
	$arr_query[$hk] = $query;
}
foreach ($arr_query2 as $hk => $query2) {
	if (empty($_REQUEST['vt']) && !empty($query2) && !preg_match('~\[.*\]~', $query2)) {
		$qs = explode(' ', $query2);
		if (!empty($_REQUEST['nd'])) {
			$query2 = '[word_nd="'.implode('"] [word_nd="', $qs).'"]';
		}
		else if (!empty($_REQUEST['lc'])) {
			$query2 = '[word_lc="'.implode('"] [word_lc="', $qs).'"]';
		}
		else {
			$query2 = '[word="'.implode('"] [word="', $qs).'"]';
		}
	}
	if (preg_match('~\b(\d+):\[.*?\1\.~', $query2)) {
		$_REQUEST['ub'] = 2;
	}
	$arr_query2[$hk] = $query2;
}

$h_query = htmlspecialchars(implode('~|~', $arr_query));
$h_query2 = htmlspecialchars(implode('~|~', $arr_query2));
$h_unbound = '1';

foreach ($arr_query as $hk => $query) {
	$query2 = $arr_query2[$hk];
	if (preg_match('~\bs_[a-z_]+!?="~', $query)) {
		$query = siblingify($query);
		$_REQUEST['ub'] = 2;
	}
	if (preg_match('~\bs_[a-z_]+!?="~', $query2)) {
		$query2 = siblingify($query2, 50);
		$_REQUEST['ub'] = 2;
	}
	if (empty($_REQUEST['ub'])) {
		$h_unbound = '';
		if (!empty($query2)) {
			$query = '('.$query2.') within (<s/> containing '.$query.')';
		}
		else {
			$query = '('.$query.') within <s/>';
		}
	}
	else if (!empty($query2)) {
		$query = '('.$query2.') within ('.$query.')';
	}
	$arr_query[$hk] = $query;
}
unset($arr_query2);

$title = '';
if (!empty($_REQUEST['c'])) {
	$title = implode(', ', array_keys($_REQUEST['c'])).' &laquo; ';
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title><?=$title;?>VISL Corpora</title>

	<script src="https://cdn.jsdelivr.net/npm/jquery@3.7/dist/jquery.min.js"></script>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/css/bootstrap.min.css">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/js/bootstrap.bundle.min.js"></script>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11/font/bootstrap-icons.css">
	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4/dist/chart.umd.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3/dist/chartjs-plugin-annotation.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/hammerjs@2"></script>
	<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2/dist/chartjs-plugin-zoom.min.js"></script>

	<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@6.6/css/flag-icons.min.css">

	<script>let g_corps = {}; let g_hash = ''; let g_hash_freq = ''; let g_hash_combo = '';</script>
	<link href="_static/refine.css?<?=filemtime(__DIR__.'/_static/refine.css');?>" rel="stylesheet">
	<script src="_static/refine.js?<?=filemtime(__DIR__.'/_static/refine.js');?>"></script>
	<link href="_static/corpus.css?<?=filemtime(__DIR__.'/_static/corpus.css');?>" rel="stylesheet">
	<script src="_static/corpus.js?<?=filemtime(__DIR__.'/_static/corpus.js');?>"></script>
</head>
<body>
<div id="logo" class="container-fluid my-3">
<a href="/" class="me-5"><img src="https://corp.visl.dk/flags/corpuseye-flat-transparent.gif"></a>
<a href="https://corp.visl.dk/cqp_help.html">Help</a>
- <a href="https://corp.visl.dk/corpuseye_manual.pdf">CorpusEye Manual</a> (pdf)
- <a href="https://corp.visl.dk/Corpuseye_IKS.pdf">Use cases</a> (Powerpoint)
- <a href="https://edu.visl.dk/tagset_cg_general.pdf">Taglist</a> (cross-language)
- <a href="https://edu.visl.dk/tagset_cg_all.pdf">Development tags</a> (unabridged)
- <a href="https://www.sketchengine.eu/documentation/corpus-querying/" target="_cql">CQL Documentation</a>
<button class="btn btn-outline-primary mx-3 btnCustomize">Adjust view <i class="bi bi-wrench-adjustable"></i></button>
</div>

<?php

if (!empty($locked)) {
	echo '<div class="container-fluid my-3"><form method="POST"><div class="row flex-nowrap align-items-start"><div class="col">';
	echo 'Please provide password(s) to access the following corpora:';
	echo '<ul>';
	foreach ($locked as $corp) {
		echo '<li><tt>'.$corp.'</tt> '.htmlspecialchars($GLOBALS['-corplist'][$corp]['name']).'</li>';
	}
	echo '</ul>';
	echo 'Multiple corpora may have the same password, in which case you only need to give it once. All passwords will be checked against all corpora.';
	echo '<ul>';
	for ($i=1 ; $i<=count($locked) ; ++$i) {
		echo '<li>Password '.$i.': <input name="p['.$i.']" type="password"></li>';
	}
	echo '</ul>';

	foreach ($_REQUEST as $k => $v) {
		if ($k === 'p') {
			continue;
		}
		if (is_array($v)) {
			foreach ($v as $sk => $sv) {
				echo '<input type="hidden" name="'.htmlspecialchars($k).'['.htmlspecialchars(strval($sk)).']" value="'.htmlspecialchars(strval($sv)).'">';
			}
		}
		else {
			echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars(strval($v)).'">';
		}
	}

	echo '<button type="submit" class="btn btn-warning">Unlock</button>';
	echo '</div></div></form></div>';
}
else if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_REQUEST['p'])) {
	unset($_REQUEST['p']);
	header('Location: ./?'.http_build_query($_REQUEST));
	die();
}
else if (!empty($_REQUEST['c']) && !empty($_REQUEST['q'])) {
	$field = $_REQUEST['f'];
	$arr_hash = [];
	$arr_hash_freq = [];
	$arr_hash_combo = [];
	$arr_folder = [];
	$arr_s_query = [];
	foreach ($arr_query as $hk => $query) {
		$hash = sha256_lc20($query);
		$hash_freq = '';
		$hash_combo = '';
		$folder = $GLOBALS['CORP_ROOT'].'/cache/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2);
		if (!is_dir($folder)) {
			mkdir($folder, 0755, true);
		}

		$arr_hash[$hk] = $hash;
		$arr_hash_freq[$hk] = '';
		$arr_hash_combo[$hk] = '';
		$arr_folder[$hk] = $folder;
		$arr_s_query[$hk] = escapeshellarg($query);
	}

	$h_corps = '';
	foreach ($_REQUEST['c'] as $corp => $_) {
		$h_corps .= '<input type="hidden" name="c['.htmlspecialchars($corp).']" value="1">';
	}

	$has_hist = true;
	$has_group = [];
	$has_word2vec = true;
	$corps = [];
	foreach ($_REQUEST['c'] as $corp => $_) {
		[$s_corp,$subc] = explode('-', $corp.'-');
		$db = new \TDC\PDO\SQLite("{$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/meta/stats.sqlite", [\PDO::SQLITE_ATTR_OPEN_FLAGS => \PDO::SQLITE_OPEN_READONLY]);
		$corps[$s_corp] = [
			'db' => $db,
			'hist' => false,
			];
		$corps[$s_corp]['hist'] = true;
		if (!intval($db->prepexec("SELECT count(*) as cnt FROM sqlite_schema WHERE name LIKE 'hist_%'")->fetchAll()[0]['cnt'])) {
			$corps[$s_corp]['hist'] = false;
			$has_hist = false;
		}
		$has_group[] = $GLOBALS['-corplist'][$s_corp]['group_by'] ?? [];
		$has_word2vec = $has_word2vec && !empty($GLOBALS['-corplist'][$s_corp]['word2vec']);
	}

	if (!empty($has_group)) {
		$has_group = array_intersect(...$has_group);
		$has_group = array_unique($has_group);
	}

	foreach ($arr_s_query as $hk => $s_query) {
		$hash = $arr_hash[$hk];
		$folder = $arr_folder[$hk];
		chdir($folder);

		// Search
		if ($_REQUEST['s'] === 's') {
			$sh = <<<XSH
#!/bin/bash
set -e
cd '$folder'

XSH;

			$exec = false;
			foreach ($_REQUEST['c'] as $corp => $_) {
				[$s_corp,$subc] = explode('-', $corp.'-');
				if (!empty($subc) && preg_match('~^[a-z0-9]+$~', $subc) && file_exists("{$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/subc/{$subc}.subc")) {
					$subc = "-u {$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/subc/{$subc}.subc";
				}
				else {
					$subc = '';
				}
				if (!file_exists("$hash-$corp.sqlite") || !filesize("$hash-$corp.sqlite")) {
					$exec = true;
				}

				$sh .= <<<XSH

if [ ! -s '$hash-$corp.sqlite' ]; then
	/usr/bin/time -f '%e' -o $hash-$corp.time timeout -k 7m 5m corpquery '{$GLOBALS['CORP_ROOT']}/registry/$s_corp' $s_query -c 0 $subc | '{$GLOBALS['WEB_ROOT']}/_bin/query2sqlite' $hash-$corp.sqlite >$hash-$corp.err 2>&1 &
fi

XSH;
		}

		$sh .= <<<XSH

for job in `jobs -p`
do
	wait \$job
done

XSH;

			$hash_sh = substr(sha256_lc20($sh), 0, 8);
			if ($exec) {
				file_put_contents("$hash-$hash_sh.sh", $sh);
				chmod("$hash-$hash_sh.sh", 0700);
				shell_exec("nice -n20 /usr/bin/time -f '%e' -o $hash-$hash_sh.time ./$hash-$hash_sh.sh >$hash-$hash_sh.err 2>&1 &");
			}
		}
		// N-grams
		else if ($_REQUEST['s'] === 'ngrams') {
			$sh = <<<XSH
#!/bin/bash
set -e
cd '$folder'

XSH;

			$exec = false;
			foreach ($_REQUEST['c'] as $corp => $_) {
				[$s_corp,$subc] = explode('-', $corp.'-');
				if (!empty($subc) && preg_match('~^[a-z0-9]+$~', $subc) && file_exists("{$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/subc/{$subc}.subc")) {
					$subc = "-u {$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/subc/{$subc}.subc";
				}
				else {
					$subc = '';
				}
				if (!file_exists("$hash-$corp.ngrams-$field.sqlite") || !filesize("$hash-$corp.ngrams-$field.sqlite")) {
					$exec = true;
				}

				$sh .= <<<XSH

if [ ! -s '$hash-$corp.ngrams-$field.sqlite' ]; then
	/usr/bin/time -f '%e' -o $hash-$corp.ngrams-$field.time timeout -k 7m 5m corpquery '{$GLOBALS['CORP_ROOT']}/registry/$s_corp' $s_query -a $field -c 0 $subc | '{$GLOBALS['WEB_ROOT']}/_bin/query2ngrams' $hash-$corp.ngrams-$field.sqlite >$hash-$corp.ngrams-$field.err 2>&1 &
fi

XSH;
		}

		$sh .= <<<XSH

for job in `jobs -p`
do
	wait \$job
done

XSH;

			$hash_sh = substr(sha256_lc20($sh), 0, 8);
			if ($exec) {
				file_put_contents("$hash-$hash_sh.ngrams.sh", $sh);
				chmod("$hash-$hash_sh.ngrams.sh", 0700);
				shell_exec("nice -n20 /usr/bin/time -f '%e' -o $hash-$hash_sh.ngrams.time ./$hash-$hash_sh.ngrams.sh >$hash-$hash_sh.ngrams.err 2>&1 &");
			}
		}
		// Frequency
		else if ($_REQUEST['s'] === 'abc' || $_REQUEST['s'] === 'freq' || $_REQUEST['s'] === 'relg' || $_REQUEST['s'] === 'relc' || $_REQUEST['s'] === 'rels') {
			$offset = $_REQUEST['o'];
			$by = $_REQUEST['b'];
			// Turn context conditions into edge conditions
			if ($by === 'lc') {
				$offset -= 1;
				$by = 'le';
			}
			else if ($by === 'rc') {
				$offset += 1;
				$by = 're';
			}

			if ($checked['pos'] && !preg_match('~^(h_)?(word|lex)(_(nd|lc))?$~', $field)) {
				$field = 'lex';
			}
			if ($checked['wv'] && $field != 'lex' && $field != 'h_lex') {
				$field = 'lex';
			}

			$which = $field;
			$nd = $field;
			if ($checked['hf'] && substr($field, 0, 2) !== 'h_') {
				$which = "h_{$which}";
				$nd = "h_{$which}";
			}

			$coll = '';
			if (!empty($_REQUEST['nd'])) {
				$checked['nd'] = 'checked';
				$checked['lc'] = 'checked';
				$_REQUEST['lc'] = '1';
				$coll = " | '{$GLOBALS['WEB_ROOT']}/_bin/collapse' nd";
				$nd .= '_nd';
				//$which .= '/i';
			}
			else if (!empty($_REQUEST['lc'])) {
				$checked['lc'] = 'checked';
				$coll = " | '{$GLOBALS['WEB_ROOT']}/_bin/collapse' lc";
				$nd .= '_lc';
				//$which .= '/i';
			}
			$s_nd = escapeshellarg($nd);

			$which .= ' '.$offset;
			if ($by === 're') {
				$which .= '>0';
			}
			if ($checked['pos'] || $checked['wv']) {
				if ($checked['hf'] || substr($field, 0, 2) == 'h_') {
					$which .= ' h_pos '.$offset;
				}
				else {
					$which .= ' pos '.$offset;
				}
				if ($by === 're') {
					$which .= '>0';
				}
			}
			if ($checked['br']) {
				$which .= ' '.$_REQUEST['br_f'].' '.$offset;
				if ($by === 're') {
					$which .= '>0';
				}
			}
			$s_which = escapeshellarg($which);
			$s_br_q = escapeshellarg($_REQUEST['br_q']);

			$hash_freq = substr(sha256_lc20($which.';'.$coll.';'.$_REQUEST['br_q']), 0, 8);
			$arr_hash_freq[$hk] = $hash_freq;

			$sh = <<<XSH
#!/bin/bash
set -e
cd '$folder'

XSH;

			$exec = false;
			$combo = [];
			foreach ($_REQUEST['c'] as $corp => $_) {
				[$s_corp,$subc] = explode('-', $corp.'-');
				if (!empty($subc) && preg_match('~^[a-z0-9]+$~', $subc) && file_exists("{$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/subc/{$subc}.subc")) {
					$subc = "{$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/subc/{$subc}.subc";
				}
				else {
					$subc = '';
				}
				$dbname = "$hash-$corp.freq-$hash_freq";
				if (!file_exists("$dbname.sqlite") || !filesize("$dbname.sqlite")) {
					$exec = true;
				}
				$lang = substr($corp, 0, 3);
				$combo[$lang][] = "$hash-$corp.freq-$hash_freq.sqlite";

				$sh .= <<<XSH

if [ ! -s '$dbname.sqlite' ]; then
	/usr/bin/time -f '%e' -o $dbname.time timeout -k 7m 5m freqs '{$GLOBALS['CORP_ROOT']}/registry/$s_corp' $s_query $s_which 0 $subc $coll | '{$GLOBALS['WEB_ROOT']}/_bin/freq2sqlite' $dbname.sqlite $corp $s_nd $s_br_q >$dbname.err 2>&1 &
fi

XSH;
			}

			$sh .= <<<XSH

for job in `jobs -p`
do
	wait \$job
done

XSH;

			// If there are multiple corpora, also calculate the combined frequencies and global relative freq
			$has_combo = false;
			if (($_REQUEST['s'] === 'abc' || $_REQUEST['s'] === 'freq' || $_REQUEST['s'] === 'relg') && count($_REQUEST['c']) > 1) {
				$hash_combo = substr(sha256_lc20(implode(';', array_keys($_REQUEST['c']))), 0, 8);
				$arr_hash_combo[$hk] = $hash_combo;

				foreach ($combo as $lang => $corps) {
					if (count($corps) <= 1) {
						continue;
					}
					$has_combo = true;
					$corps = implode(' ', $corps);
					$corp = $lang.'_0combo_'.$hash_combo;
					$_REQUEST['c'][$corp] = 1;
					$dbname = "$hash-$corp.freq-$hash_freq";
					if (!file_exists("$dbname.sqlite") || !filesize("$dbname.sqlite")) {
						$exec = true;
					}
					$sh .= <<<XSH

if [ ! -s '$dbname.sqlite' ]; then
	/usr/bin/time -f '%e' -o $dbname.time timeout -k 7m 5m '{$GLOBALS['WEB_ROOT']}/_bin/combinefreqs' $dbname.sqlite $lang $s_nd $corps >$dbname.err 2>&1 &
fi

XSH;
				}
			}

			if ($has_combo) {
				ksort($_REQUEST['c']);
				$sh .= <<<XSH

for job in `jobs -p`
do
	wait \$job
done

XSH;
			}

			$hash_sh = substr(sha256_lc20($sh), 0, 8);
			if ($exec) {
				file_put_contents("$hash-$hash_sh.sh", $sh);
				chmod("$hash-$hash_sh.sh", 0700);
				shell_exec("nice -n20 /usr/bin/time -f '%e' -o $hash-$hash_sh.time ./$hash-$hash_sh.sh >$hash-$hash_sh.err 2>&1 &");
			}
		}
		// Histogram
		else if ($_REQUEST['s'] === 'hist') {
			$sh = <<<XSH
#!/bin/bash
set -e
cd '$folder'

XSH;

			$exec = false;
			foreach ($_REQUEST['c'] as $corp => $_) {
				[$s_corp,$subc] = explode('-', $corp.'-');
				if (!empty($subc) && preg_match('~^[a-z0-9]+$~', $subc) && file_exists("{$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/subc/{$subc}.subc")) {
					$subc = "-u {$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/subc/{$subc}.subc";
				}
				else {
					$subc = '';
				}
				if (!file_exists("$hash-$corp.hist.sqlite") || !filesize("$hash-$corp.hist.sqlite")) {
					$exec = true;
				}

				$sh .= <<<XSH

if [ ! -s '$hash-$corp.hist.sqlite' ]; then
	/usr/bin/time -f '%e' -o $hash-$corp.hist.time timeout -k 7m 5m '{$GLOBALS['WEB_ROOT']}/_bin/corpquery-histogram' '{$GLOBALS['CORP_ROOT']}/registry/$s_corp' $s_query -c 0 $subc | grep -v '===NONE===' | '{$GLOBALS['WEB_ROOT']}/_bin/histogram' $hash-$corp.hist.sqlite >$hash-$corp.hist.err 2>&1 &
fi

XSH;
		}

		$sh .= <<<XSH

for job in `jobs -p`
do
	wait \$job
done

XSH;

			$hash_sh = substr(sha256_lc20($sh), 0, 8);
			if ($exec) {
				file_put_contents("$hash-$hash_sh.hist.sh", $sh);
				chmod("$hash-$hash_sh.hist.sh", 0700);
				shell_exec("nice -n20 /usr/bin/time -f '%e' -o $hash-$hash_sh.hist.time ./$hash-$hash_sh.hist.sh >$hash-$hash_sh.hist.err 2>&1 &");
			}
		}
		// Group By
		else if ($_REQUEST['s'] === 'group') {
			$gs = implode(':', $has_group);
			$hash_gs = 'ALL';
			if ($_REQUEST['ga'] === 'tt') {
				$gs = [];
				for ($i=0 ; $i<10 ; ++$i) {
					if (preg_match('~^[_a-zA-Z]+$~', $_REQUEST["g{$i}"] ?? '')) {
						$gs[] = $_REQUEST["g{$i}"];
					}
				}
				$gs = implode(':', $gs);
				$hash_gs = substr(sha256_lc20($gs), 0, 8);
			}

			$sh = <<<XSH
#!/bin/bash
set -e
cd '$folder'

XSH;
			$s_br = '';
			$s_br_q = escapeshellarg($_REQUEST['br_q']);
			if ($checked['br']) {
				$s_br = '-r '.escapeshellarg($_REQUEST['br_f']);
			}

			$exec = false;
			foreach ($_REQUEST['c'] as $corp => $_) {
				[$s_corp,$subc] = explode('-', $corp.'-');
				if (!empty($subc) && preg_match('~^[a-z0-9]+$~', $subc) && file_exists("{$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/subc/{$subc}.subc")) {
					$subc = "-u {$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/subc/{$subc}.subc";
				}
				else {
					$subc = '';
				}
				if (!file_exists("$hash-$corp.group-$hash_gs.sqlite") || !filesize("$hash-$corp.group-$hash_gs.sqlite")) {
					$exec = true;
				}

				$sh .= <<<XSH

if [ ! -s '$hash-$corp.group-$hash_gs.sqlite' ]; then
	/usr/bin/time -f '%e' -o $hash-$corp.group-$hash_gs.time timeout -k 7m 5m '{$GLOBALS['WEB_ROOT']}/_bin/corpquery-groupby' '{$GLOBALS['CORP_ROOT']}/registry/$s_corp' $s_query $s_br -c 0 $subc | grep -v '===NONE===' | '{$GLOBALS['WEB_ROOT']}/_bin/group-by' $hash-$corp.group-$hash_gs.sqlite '$gs' $s_br_q >$hash-$corp.group-$hash_gs.err 2>&1 &
fi

XSH;
		}

		$sh .= <<<XSH

for job in `jobs -p`
do
	wait \$job
done

XSH;

			$hash_sh = substr(sha256_lc20($sh), 0, 8);
			if ($exec) {
				file_put_contents("$hash-$hash_sh.group-$hash_gs.sh", $sh);
				chmod("$hash-$hash_sh.group-$hash_gs.sh", 0700);
				shell_exec("nice -n20 /usr/bin/time -f '%e' -o $hash-$hash_sh.group-$hash_gs.time ./$hash-$hash_sh.group-$hash_gs.sh >$hash-$hash_sh.group-$hash_gs.err 2>&1 &");
			}
		}
		// word2vec, vector
		else if ($_REQUEST['s'] === 'wv') {
			$xs = "{$_REQUEST['x1']}~{$_REQUEST['x2']}~{$_REQUEST['y1']}~{$_REQUEST['y2']}";
			$ws = escapeshellarg(str_replace(' ', '=', $_REQUEST['ws']));

			$sh = <<<XSH
#!/bin/bash
set -e
cd '$folder'

XSH;

			$axes = [];
			$hash_q = substr(sha256_lc20("{$xs}:{$ws}"), 0, 8);
			$exec = false;
			foreach ($_REQUEST['c'] as $corp => $_) {
				[$s_corp,$subc] = explode('-', $corp.'-');
				if (!empty($subc) && preg_match('~^[a-z0-9]+$~', $subc) && file_exists("{$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/subc/{$subc}.subc")) {
					$subc = "{$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/subc/{$subc}.subc";
				}
				else {
					$subc = '';
				}
				if (!file_exists("$hash-$hash_q-$s_corp.wv") || !filesize("$hash-$hash_q-$s_corp.wv")) {
					$exec = true;
				}

				$axes[$s_corp] = [
					'x1' => [],
					'x2' => [],
					'y1' => [],
					'y2' => [],
					];
				$xs = [];
				foreach ($axes[$s_corp] as $ax => $_) {
					$hash_ax = substr(sha256_lc20($_REQUEST[$ax]), 0, 8);
					$did = false;
					$read = false;

					if (!file_exists("$hash-$hash_q-$s_corp-$hash_ax.$ax") || !filesize("$hash-$hash_q-$s_corp-$hash_ax.$ax")) {
						$as = explode(';', $_REQUEST[$ax]);
						foreach ($as as $w) {
							$q = '';
							if (!preg_match('~[.*+?^${}()|\[\]]~', $w)) {
								$axes[$s_corp][$ax][] = $w;
								$xs[] = $w;
								continue;
							}
							$did = true;
							if (preg_match('~^(.+?)_([^_]+)$~', $w, $m)) {
								$q = '[lex="'.$m[1].'" & pos="'.$m[2].'"]';
							}
							else {
								$q = '[lex="'.$w.'"]';
							}
							$s_q = escapeshellarg($q);
							shell_exec("nice -n20 timeout -k 7m 5m freqs '{$GLOBALS['CORP_ROOT']}/registry/$s_corp' $s_q 'lex 0 pos 0' 5 $subc >> '$hash-$hash_q-$s_corp-$hash_ax.$ax.tmp'");
						}
						if ($did) {
							shell_exec("cat '$hash-$hash_q-$s_corp-$hash_ax.$ax.tmp' | sort '-t	' -k3 -nr | head -n 100 > '$hash-$hash_q-$s_corp-$hash_ax.$ax'");
							//unlink("$hash-$hash_q-$s_corp-$hash_ax.$ax.tmp");
							$read = true;
						}
					}
					else {
						$read = true;
					}

					if ($read) {
						$aws = explode("\n", trim(file_get_contents("$hash-$hash_q-$s_corp-$hash_ax.$ax")));
						foreach ($aws as $aw) {
							if (preg_match('~^([^\t]+)\t([^\t]+)~', $aw, $m)) {
								$m[1] = str_replace(' ', '=', $m[1]);
								$axes[$s_corp][$ax][] = $m[1].'_'.$m[2];
								$xs[] = $m[1].'_'.$m[2];
							}
						}
					}
				}

				$xs = escapeshellarg(str_replace(' ', '=', implode('~', $xs)));

				$sh .= <<<XSH

if [ ! -s '$hash-$hash_q-$s_corp.wv' ]; then
	/usr/bin/time -f '%e' -o $hash-$hash_q-$s_corp.wv.time timeout -k 7m 5m ssh 'manatee@backends.gramtrans.com' '{$GLOBALS['CORP_ROOT']}/venv/bin/python3' '{$GLOBALS['WEB_ROOT']}/_bin/word2vec-query' '{$GLOBALS['CORP_ROOT']}/word2vec/$s_corp/model.300.sg.w2v' $xs $ws >$hash-$hash_q-$s_corp.wv 2>$hash-$hash_q-$s_corp.wv.err &
fi

XSH;
		}

		$sh .= <<<XSH

for job in `jobs -p`
do
	wait \$job
done

XSH;

			$hash_sh = substr(sha256_lc20($sh), 0, 8);
			if ($exec) {
				file_put_contents("$hash-$hash_sh.wv.sh", $sh);
				chmod("$hash-$hash_sh.wv.sh", 0700);
				shell_exec("nice -n20 /usr/bin/time -f '%e' -o $hash-$hash_sh.wv.time ./$hash-$hash_sh.wv.sh >$hash-$hash_sh.wv.err 2>&1");
			}

			$json = [];
			foreach ($_REQUEST['c'] as $corp => $_) {
				[$s_corp,$subc] = explode('-', $corp.'-');
				$data = [
					'axes' => $axes[$s_corp],
					'data' => [],
					];
				$lines = explode("\n", trim(file_get_contents("$folder/$hash-$hash_q-$s_corp.wv")));
				$last = '';
				foreach ($lines as $line) {
					if ($line[0] !== "\t") {
						$last = $line;
					}
					else {
						$line = explode("\t", $line);
						$data['data'][$last][$line[1]] = floatval($line[2]);
					}
				}
				$json[$s_corp] = $data;
			}

			echo '<script>let g_wv = '.json_encode_vb($json).';</script>';
		}
	}

	$bys = [
		'lc' => 'Left context',
		'rc' => 'Right context',
		'le' => 'Left edge',
		're' => 'Right edge',
		];
	$by_sel = '';
	foreach ($bys as $k => $v) {
		$sel = '';
		if ($k === $_REQUEST['b']) {
			$sel = ' selected';
		}
		$by_sel .= '<option value="'.$k.'"'.$sel.'>'.htmlspecialchars($v).'</option>'."\n";
	}

	$off_sel = '';
	for ($i=-4 ; $i<5 ; ++$i) {
		$sel = '';
		if ($i === $_REQUEST['o']) {
			$sel = ' selected';
		}
		$off_sel .= '<option value="'.$i.'"'.$sel.'>'.$i.'</option>'."\n";
	}

	$word2vec = '';
	if ($has_word2vec) {
		$word2vec = <<<XHTML
<div class="my-3 form-check">
<input class="form-check-input" type="checkbox" name="wv" id="wv" {$checked['wv']}>
<label class="form-check-label" for="wv">Vector plottable</label>
</div>

XHTML;
	}

	// Sidebar
	echo '<div class="container-fluid my-3"><div class="row flex-nowrap align-items-start"><div class="col sidebar">';
	// Frequency & N-grams
	echo <<<XHTML
<div class="card bg-lightblue mb-3">
<div class="card-header text-center fw-bold fs-6">
Frequency <i class="bi bi-sort-down"></i>
</div>
<div class="card-body">
<form method="GET" id="formFreq">
<input type="hidden" name="l" value="{$h_language}">
<input type="hidden" name="q" value="{$h_query}">
<input type="hidden" name="q2" value="{$h_query2}">
<input type="hidden" name="ub" value="{$h_unbound}">
{$h_corps}
<div class="text-center">
<button class="btn btn-sm btn-success mb-1" id="btnAbc" type="submit" name="s" value="abc" title="Sort alphabetically">Sort</button>
<button class="btn btn-sm btn-success mb-1" id="btnFreq" type="submit" name="s" value="freq" title="Sort by absolute frequency">Freq</button>
<br>
<button class="btn btn-sm btn-success btnRel" id="btnRelG" type="submit" name="s" value="relg" title="Sort by relative frequency (global)" disabled>Rel G</button>
<button class="btn btn-sm btn-success btnRel" id="btnRelC" type="submit" name="s" value="relc" title="Sort by relative frequency (corpus)" disabled>Rel C</button>
<button class="btn btn-sm btn-success btnRel" id="btnRelS" type="submit" name="s" value="rels" title="Sort by relative frequency (sub-corpus)" disabled>Rel S</button>
</div>
<div class="my-3">
<label class="form-label" for="freq_field">Field</label>
<a tabindex="0" role="button" class="float-right" data-bs-toggle="popover" data-bs-container="body" data-bs-content="The field to focus statistical analysis on"><i class="bi bi-question-square"></i></a>
<select class="form-select" name="f" id="freq_field">
	{$freq_fields}
</select>
</div>
<div class="my-3 form-check">
<input class="form-check-input" type="checkbox" name="hf" id="hf" {$checked['hf']}>
<label class="form-check-label" for="hf">Dependency head field</label>
</div>
<div class="my-3">
<label class="form-label" for="freq_by">By</label>
<a tabindex="0" role="button" class="float-right" data-bs-toggle="popover" data-bs-container="body" data-bs-content="What to sort by. Edge words are the first and last words in the highlighted hit string, context words are those just outside."><i class="bi bi-question-square"></i></a>
<select class="form-select" name="b" id="freq_by" size="4">
	{$by_sel}
</select>
</div>
<div class="my-3">
<label class="form-label" for="freq_offset">Offset</label>
<a tabindex="0" role="button" class="float-right" data-bs-toggle="popover" data-bs-container="body" data-bs-content="Position right [+] or left [-] of edge or context word."><i class="bi bi-question-square"></i></a>
<select class="form-select" name="o" id="freq_offset">
	{$off_sel}
</select>
</div>
<div class="my-3">
<label class="form-label" for="freq_cutoff">Absolute cutoff</label>
<a tabindex="0" role="button" class="float-right" data-bs-toggle="popover" data-bs-container="body" data-bs-content="Removes entries with less-than this amount of absolute hits"><i class="bi bi-question-square"></i></a>
<input class="form-control" name="cut" id="freq_cutoff" value="{$_REQUEST['cut']}">
</div>
<div class="my-3 form-check">
	<input class="form-check-input" type="checkbox" name="lc" id="lc" {$checked['lc']}>
	<label class="form-check-label" for="lc">Collapse case</label>
</div>
<div class="my-3 form-check">
	<input class="form-check-input" type="checkbox" name="nd" id="nd" {$checked['nd']}>
	<label class="form-check-label" for="nd">Collapse diacritics</label>
</div>
<div class="my-3 form-check">
	<input class="form-check-input" type="checkbox" name="pos" id="pos" {$checked['pos']}>
	<label class="form-check-label" for="pos">Distinguish part-of-speech</label>
</div>
{$word2vec}
<div class="my-3 form-check">
	<input class="form-check-input" type="checkbox" name="br" id="br" {$checked['br']}>
	<label class="form-check-label" for="br">Lump results</label>
	<a tabindex="0" role="button" class="float-right" data-bs-toggle="popover" data-bs-container="body" data-bs-content="Lump results by matching regexes against all tags in a field. Only the first tag that matches counts - a result is exclusive to one regex. Separate the regexes by semicolon."><i class="bi bi-question-square"></i></a>
</div>
<div class="collapse bracket">
	<div class="my-3">
		<label class="form-label" for="br_freq_field">Field</label>
		<a tabindex="0" role="button" class="float-right" data-bs-toggle="popover" data-bs-container="body" data-bs-content="The field to lump matches by"><i class="bi bi-question-square"></i></a>
		<select class="form-select" name="br_f" id="br_freq_field">
			{$br_freq_fields}
		</select>
	</div>
	<div class="my-3">
		<input class="form-control" type="text" name="br_q" value="{$h_br_q}">
	</div>
</div>
</form>
</div></div>

<div class="card bg-lightblue mb-3">
<div class="card-header text-center fw-bold fs-6">
N-grams <i class="bi bi-list-ol"></i>
</div>
<div class="card-body">
<form method="GET" id="ngrams">
<input type="hidden" name="l" value="{$h_language}">
<input type="hidden" name="q" value="{$h_query}">
<input type="hidden" name="q2" value="{$h_query2}">
<input type="hidden" name="ub" value="{$h_unbound}">
{$h_corps}
<div class="text-center">
<button class="btn btn-sm btn-success mb-1" type="submit" name="s" value="ngrams">N-grams</button>
</div>
<div class="my-3">
<label class="form-label" for="freq_field">Field</label>
<a tabindex="0" role="button" class="float-right" data-bs-toggle="popover" data-bs-container="body" data-bs-content="The field to show"><i class="bi bi-question-square"></i></a>
<select class="form-select" name="f" id="freq_field">
	{$freq_fields}
</select>
</div>
</form>
</div></div>

XHTML;

	// Histogram
	if ($has_hist) {
		echo <<<XHTML
<div class="card bg-lightblue mb-3">
<form method="GET">
<input type="hidden" name="l" value="{$h_language}">
<input type="hidden" name="q" value="{$h_query}">
<input type="hidden" name="q2" value="{$h_query2}">
<input type="hidden" name="ub" value="{$h_unbound}">
{$h_corps}
<div class="card-header text-center fw-bold fs-6">
Histogram <i class="bi bi-hourglass"></i>
</div>
<div class="card-body">
<div class="text-center"><button class="btn btn-sm btn-success mb-3" type="submit" name="s" value="hist" title="Group results into a histogram">Chart histogram</button></div>
<div class="mb-3"><label for="qhistgroup" class="form-label">Group by</label><select class="form-select" name="g" id="qhistgroup" size="5">
<option value="Y">Year</option>
<option value="Y-m">Year-Month</option>
<option value="Y-m-d" selected>Year-Month-Day</option>
<option value="Y-m-d H">Year-Month-Day Hour</option>
<option value="Y H">Year Hour-of-day</option>
</select></div>
<div class="my-3 form-check">
<input class="form-check-input" type="checkbox" name="ha" id="ha" {$checked['ha']}>
<label class="form-check-label" for="ha">Aggregate articles instead of sentences</label>
</div>
<div class="my-3 form-check">
<input class="form-check-input" type="checkbox" name="xe" id="xe" {$checked['xe']}>
<label class="form-check-label" for="xe">Expand empty ranges</label>
</div>
<div class="my-3 form-check">
<input class="form-check-input" type="checkbox" name="xs" id="xs" {$checked['xs']}>
<label class="form-check-label" for="xs">Expand sparse ranges</label>
</div>
</div>
</form>
</div>

XHTML;
	}

	// Group by
	if (!empty($has_group)) {
		echo <<<XHTML
<div class="card bg-lightblue mb-3">
<form method="GET" id="formGroupBy">
<input type="hidden" name="l" value="{$h_language}">
<input type="hidden" name="q" value="{$h_query}">
<input type="hidden" name="q2" value="{$h_query2}">
<input type="hidden" name="ub" value="{$h_unbound}">
<input type="hidden" name="br" value="{$checked['br']}">
<input type="hidden" name="br_f" value="{$_REQUEST['br_f']}">
<input type="hidden" name="br_q" value="{$h_br_q}">
{$h_corps}
<div class="card-header text-center fw-bold fs-6">
Group By <i class="bi bi-bar-chart-steps"></i>
</div>
<div class="card-body">
<div class="text-center"><button class="btn btn-sm btn-success mb-3" type="submit" name="s" value="group" id="btnGroupBy">Group results</button></div>
<div><label class="form-label">By attributes</label></div>

XHTML;
		foreach ($has_group as $gk => $gv) {
			$gi = $gk + 1;
			echo <<<XHTML
<div class="mb-3"><select class="form-select" name="g{$gk}">

XHTML;
			if ($gk > 0) {
				echo '<option value="">(none)</option>';
			}
			foreach ($has_group as $av) {
				$sel = '';
				if (!empty($_REQUEST["g{$gk}"]) && $_REQUEST["g{$gk}"] === $av) {
					$sel = ' selected';
				}
				echo '<option value="'.$av.'"'.$sel.'>'.$av.'</option>';
			}
			echo '</select></div>';
		}
		echo <<<XHTML
<div><label class="form-label" for="ga">Compare hits per</label></div>
<div class="mb-3">
<select class="form-select" size="3" name="ga" id="ga">

XHTML;
		$gas = [
			's' => 'Sentences',
			'a' => 'Articles',
			'w' => '10k words',
			'tt' => 'Type/token',
			];
		foreach ($gas as $ck => $cv) {
			$sel = '';
			if ($_REQUEST['ga'] === $ck) {
				$sel = ' selected';
			}
			echo '<option value="'.$ck.'"'.$sel.'>'.$cv.'</option>';
		}
		echo <<<XHTML
</select>
</div>
<div class="my-3 form-check">
<input class="form-check-input" type="checkbox" name="xe" id="xe" {$checked['xe']}>
<label class="form-check-label" for="xe">Expand empty ranges</label>
</div>
<div class="my-3 form-check">
<input class="form-check-input" type="checkbox" name="xs" id="xs" {$checked['xs']}>
<label class="form-check-label" for="xs">Hide sparse ranges</label>
</div>
</div></form></div>

XHTML;
	}

	// Distribute
	echo <<<XHTML
<!--
<div class="card bg-lightblue mb-3">
<form method="GET" id="formDistribute">
<input type="hidden" name="l" value="{$h_language}">
<input type="hidden" name="q" value="{$h_query}">
<input type="hidden" name="q2" value="{$h_query2}">
<input type="hidden" name="ub" value="{$h_unbound}">
{$h_corps}
<div class="card-header text-center fw-bold fs-6">
Distribute <i class="bi bi-bar-chart"></i>
</div>
<div class="card-body">
<div class="text-center"><button class="btn btn-sm btn-success mb-3" type="submit" name="s" value="dist" id="btnDistribute">Distribute results</button></div>
<div class="my-3 form-check">
<input class="form-check-input" type="checkbox" name="dc" id="dc" {$checked['dc']}>
<label class="form-check-label" for="dc">Whole corpus instead of contiguous &lt;s&gt; range</label>
</div>
<div class="my-3 form-check">
<input class="form-check-input" type="checkbox" name="dt" id="dt" {$checked['dt']}>
<label class="form-check-label" for="dt">Aggregate tokens instead of sentences</label>
</div>
</div></form></div>
-->

XHTML;

	// Page size & Focus field
	echo <<<XHTML
<div class="card bg-lightblue mb-3">
<div class="card-header text-center fw-bold fs-6">
Other <i class="bi bi-sliders"></i>
</div>
<div class="card-body">
<div class="mb-3"><label for="qpagesize" class="form-label">Page size</label><select class="form-select" id="qpagesize"><option value="50">50</option><option value="100">100</option><option value="200">200</option><option value="300">300</option><option value="400">400</option><option value="500">500</option><option value="1000">1000</option><option value="2000">2000</option><option value="3000">3000</option><option value="4000">4000</option><option value="5000">5000</option></select></div>
XHTML;
	if ($_REQUEST['s'] === 's') {
		echo '<div class="my-3"><label class="form-label" for="qfocus">Focus field</label><select class="form-select" id="qfocus">'.$fields.'</select></div>';
	}
	echo '<div class="text-center my-3"><button type="button" class="btn btn-sm btn-success btnRefine">Refine <i class="bi bi-funnel"></i></button></div>';

	if ($_REQUEST['s'] !== 's') {
		echo <<<XHTML
<div class="text-center mt-3">
<form method="GET">
<input type="hidden" name="l" value="{$h_language}">
<input type="hidden" name="q" value="{$h_query}">
<input type="hidden" name="q2" value="{$h_query2}">
<input type="hidden" name="ub" value="{$h_unbound}">
{$h_corps}
<button class="btn btn-sm btn-success mb-3" type="submit" name="s" value="s" title="Back to concordance">Back to concordance</button>
</form>
</div>

XHTML;
	}
	echo '</div></div>';
	echo '</div>';

	// Body of results
	echo '<div class="col">';
	echo '<div class="container-fluid my-3">';
	echo '<div class="row"><div class="col qpages">…</div><div class="col"><button class="btn btn-outline-primary my-1 btnShowSearch">Show search <i class="bi bi-search"></i></button> <button class="btn btn-outline-primary my-1 btnRefine">Show refine <i class="bi bi-funnel"></i></button></div></div>';
	echo <<<XHTML
<div class="row align-items-start my-3" id="customize-freq" style="display: none">
<h5 class="fw-bold fs-5">Toggle columns</h5>
<div class="col ps-4">
	<div><label class="form-check-label"><input type="checkbox" class="form-check-input arrOptVisible" value="qcol-grf" checked> Global relative frequency (<span class="color-red">G: freq²∕norm</span>)</label></div>
	<div><label class="form-check-label"><input type="checkbox" class="form-check-input arrOptVisible" value="qcol-crf" checked> Corpus relative frequency (<span class="color-red">C: freq²∕norm</span>)</label></div>
	<div><label class="form-check-label"><input type="checkbox" class="form-check-input arrOptVisible" value="qcol-scrf" checked> Sub-corpus relative frequency (<span class="color-red">S: freq²∕norm</span>)</label></div>
	<div><label class="form-check-label"><input type="checkbox" class="form-check-input arrOptVisible" value="qcol-nf" checked> Global/Corpus normalized frequency (<span class="color-orange">G|C: freq∕corp · 10⁸</span>)</label></div>
	<div><label class="form-check-label"><input type="checkbox" class="form-check-input arrOptVisible" value="qcol-scnf" checked> Sub-corpus normalized frequency (<span class="color-orange">S: freq∕corp · 10⁸</span>)</label></div>
	<div><label class="form-check-label"><input type="checkbox" class="form-check-input arrOptVisible" value="qcol-pcnt" checked> Percentage of total hits (<span class="color-green">freq∕conc</span>)</label></div>
	<div><label class="form-check-label"><input type="checkbox" class="form-check-input arrOptVisible" value="qcol-num" checked> Number of hits (num)</label></div>
</div>
</div>

XHTML;
	echo '<div class="row align-items-start" id="search-holder" style="display: none"></div>';
	echo '<div class="row align-items-start row-cols-auto">';
	if ($_REQUEST['s'] === 's') {
		foreach ($_REQUEST['c'] as $corp => $_) {
			echo '<div class="col qresults qcorpus" id="'.htmlspecialchars($corp).'"><div class="qhead text-center fs-5"><span class="qcname fw-bold fs-4">'.htmlspecialchars($corp).'</span><br><span class="qrange">…</span> of <span class="qtotal">…</span></div><div class="qbody">…searching…</div></div>';
		}
	}
	else if ($_REQUEST['s'] === 'ngrams') {
		foreach ($_REQUEST['c'] as $corp => $_) {
			echo '<div class="col qngrams qcorpus" id="'.htmlspecialchars($corp).'"><div class="qhead text-center fs-5"><span class="qcname fw-bold fs-4">'.htmlspecialchars($corp).'</span><br><span class="qrange">…</span> of <span class="qtotal">…</span></div><div class="qbody">…searching…</div></div>';
		}
	}
	else if ($_REQUEST['s'] === 'abc' || $_REQUEST['s'] === 'freq' || $_REQUEST['s'] === 'relg' || $_REQUEST['s'] === 'relc' || $_REQUEST['s'] === 'rels') {
		if (count($_REQUEST['c']) > 1 && ($_REQUEST['s'] === 'relc' || $_REQUEST['s'] === 'rels')) {
			$toasts[] = '<div class="toast align-items-center text-bg-warning bg-opacity-50" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000"><div class="d-flex"><div class="toast-body">Combined results will not show for <i>Rel C</i> or <i>Rel S</i>.</div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div></div>';
		}
		foreach ($_REQUEST['c'] as $corp => $_) {
			$cname = $corp;
			if (strpos($corp, '_0combo_') !== false) {
				$cname = 'Combined ('.substr($corp, 0, 3).')';
			}
			else if ($_REQUEST['s'] === 'rels' && strpos($corp, '-') === false) {
				$toasts[] = '<div class="toast align-items-center text-bg-warning bg-opacity-50" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000"><div class="d-flex"><div class="toast-body">Corpus '.$corp.' has no sub-corpus for <i>Rel S</i>.</div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div></div>';
				continue;
			}
			echo '<div class="col qfreqs qcorpus" id="'.$corp.'"><div class="d-flex">
			<div class="col qhead text-center fs-5"><span class="qcname fw-bold fs-4">'.htmlspecialchars($cname).'</span><br><span class="qrange">…</span> of <span class="qtotal">…</span></div><div class="col text-end qtsv">…</div></div><div class="qbody">…searching…</div></div>';
			if (strpos($corp, '_0combo_') !== false) {
				echo '<div class="w-100"></div>';
			}
		}
	}
	else if ($_REQUEST['s'] === 'hist') {
		foreach ($_REQUEST['c'] as $corp => $_) {
			[$s_corp,$subc] = explode('-', $corp.'-');
			echo '<div class="col qhistgraph" id="graph-'.htmlspecialchars($s_corp).'-subc"><div class="qhead text-center fs-5"><span class="qcname fw-bold fs-4"></span><div class="qbody"></div></div></div>';
			echo '<div class="col qhistgraph" id="graph-'.htmlspecialchars($corp).'"><div class="qhead text-center fs-5"><span class="qcname fw-bold fs-4"></span><div class="qbody"></div></div></div>';
		}
		echo '</div>';
		echo '<div class="row">';
		foreach ($_REQUEST['c'] as $corp => $_) {
			echo '<div class="col qhist qcorpus" id="'.htmlspecialchars($corp).'"><div class="qhead text-center fs-5"><span class="qcname fw-bold fs-4">'.htmlspecialchars($corp).'</span><div class="qbody">…searching…</div></div></div>';
		}
	}
	else if ($_REQUEST['s'] === 'group') {
		foreach ($_REQUEST['c'] as $corp => $_) {
			[$s_corp,$subc] = explode('-', $corp.'-');
			echo '<div class="col qgroupgraph" id="graph-'.htmlspecialchars($s_corp).'-subc"><div class="qhead text-center fs-5"><span class="qcname fw-bold fs-4"></span><div class="qbody"></div></div></div>';
			echo '<div class="col qgroupgraph" id="graph-'.htmlspecialchars($corp).'"><div class="qhead text-center fs-5"><span class="qcname fw-bold fs-4"></span><div class="qbody"></div></div></div>';
		}
		echo '</div>';
		echo '<div class="row">';
		foreach ($_REQUEST['c'] as $corp => $_) {
			echo '<div class="col qgroup qcorpus" id="'.htmlspecialchars($corp).'"><div class="qhead text-center fs-5"><span class="qcname fw-bold fs-4">'.htmlspecialchars($corp).'</span><div class="qbody">…searching…</div></div></div>';
		}
	}
	else if ($_REQUEST['s'] === 'wv') {
		foreach ($_REQUEST['c'] as $corp => $_) {
			[$s_corp,$subc] = explode('-', $corp.'-');
			echo '<div class="col qwordvec" id="wv-'.htmlspecialchars($s_corp).'"><div class="qhead text-center fs-5"><span class="qcname fw-bold fs-4">'.htmlspecialchars($s_corp).'</span><div class="qbody"></div></div></div>';
		}
		echo '</div>';
	}
	echo '</div>';
	echo '<div class="row"><div class="col qpages">…</div></div>';
	echo '</div>';
	echo '</div></div></div>';
	echo '<script>g_corps = '.json_encode_vb($_REQUEST['c']).'; g_hash = "'.implode(';', $arr_hash).'"; g_hash_freq = "'.implode(';', $arr_hash_freq).'"; g_hash_combo = "'.implode(';', $arr_hash_combo).'";</script>';
}

?>

<div class="container card bg-lightblue my-2" id="search">
<?php
if (empty($h_language)) {
	echo '<div class="row align-items-start row-cols-auto">';
	foreach ($GLOBALS['-groups'] as $group => $g) {
		$total_ws = 0;
		$flag = '<span class="fi fi-'.$g['flag'].'"></span>';
		if (strlen($g['flag']) > 2) {
			$flag = '<span class="fi" style="background-image: url('.$g['flag'].')"></span>';
		}

		echo '<div class="col-3 my-3"><a class="text-decoration-none fs-3" href="./?l='.$group.'">'.$flag.' '.htmlspecialchars($g['name']).'</a></div>';
	}
	echo '<div class="col-12 my-3"><a class="text-decoration-none fs-3" href="./?l=mul"><i class="bi bi-translate"></i> All languages</a></div>';
	echo '</div>';
}
else {
?>
<form method="GET">
<input type="hidden" name="l" value="<?=$h_language;?>">
<div class="row justify-content-center align-items-center row-cols-auto" id="refine">
<div class="col">
<div id="rs" class="rs">

<table id="r" class="etable">
<tr>
<td rowspan="4" class="middle"><button type="button" onclick="refine.insert_before(this);">+</button></td>
<td class="center colored">
	<table class="inbox">
	<tr>
	<td><abbr class="where" title="Sub-query: Further narrowing within the sentences matched by the above query"></abbr></td>
	<td class="topbox"><label><input type="radio" name="n" value="" checked> 1</label></td>
	<td class="topbox"><label><input type="radio" name="n" value="+"> +</label></td>
	<td class="topbox"><label><input type="radio" name="n" value="?"> ?</label></td>
	<td class="topbox"><label><input type="radio" name="n" value="*"> *</label></td>
	</tr>
	</table>
</td>
<td rowspan="4" class="middle"><button type="button" onclick="refine.insert_after(this);">+</button></td>
</tr>
<tr>
<td class="colored"><table><tr><td class="midbox colored"></td><td class="sibbox colored hidden"></td></tr></table></td>
</tr>
<tr class="depbox colored hidden">
<td></td>
</tr>
<tr>
<td class="center"><button type="button" onclick="refine.toggle_dependency(this);">Dep Head</button> &nbsp;
<span class="btnSibling"><button type="button" onclick="refine.toggle_sibling(this);">Sibling</button> &nbsp; </span>
<button type="button" onclick="refine.delete_table(this);">Delete</button></td>
</tr>
</table>

</div>

<div id="rs2" class="rs">
</div>

<div class="d-flex justify-content-center mb-3">
	<div class="mx-3"><button class="btn btn-primary" type="button" id="toggle_sq">Toggle Sub-Query</button></div>
	<div class="mx-3 collapse show meta-fields"><button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target=".meta-fields">Show meta attributes</button></div>
	<div class="mx-3 collapse meta-fields">
		<h5>Meta attributes <a href="https://www.sketchengine.eu/documentation/cql-search-structures/" target="_blank"><i class="bi bi-question-square"></i></a></h5>
		<div class="row">
			<div class="col-3"><label class="form-label" for="meta-author">Author</label></div>
			<div class="col"><input type="text" class="form-control d-inline-block" id="meta-author" data-sattr="author"></div><div class="col-1 text-nowrap"><label title="Invert match"><input type="checkbox" class="form-check-input meta-neg" id="meta-author-neg">¬</label></div>
		</div>
		<div class="row">
			<div class="col-3"><label class="form-label" for="meta-title">Title</label></div>
			<div class="col text-nowrap"><input type="text" class="form-control d-inline-block" id="meta-title" data-sattr="title"></div><div class="col-1 text-nowrap"><label title="Invert match"><input type="checkbox" class="form-check-input meta-neg" id="meta-title-neg">¬</label></div>
		</div>
		<div class="row">
			<div class="col-3"><label class="form-label" for="meta-year">Year</label></div>
			<div class="col text-nowrap"><input type="text" class="form-control d-inline-block" id="meta-year" data-sattr="year"></div><div class="col-1 text-nowrap"><label title="Invert match"><input type="checkbox" class="form-check-input meta-neg" id="meta-year-neg">¬</label></div>
		</div>
		<div class="row">
			<div class="col-3"><label class="form-label" for="meta-publisher">Publisher</label></div>
			<div class="col text-nowrap"><input type="text" class="form-control d-inline-block" id="meta-publisher" data-sattr="publisher"></div><div class="col-1 text-nowrap"><label title="Invert match"><input type="checkbox" class="form-check-input meta-neg" id="meta-publisher-neg">¬</label></div>
		</div>
		<div class="row">
			<div class="col-3"><label class="form-label" for="meta-translator">Translator</label></div>
			<div class="col text-nowrap"><input type="text" class="form-control d-inline-block" id="meta-translator" data-sattr="translator"></div><div class="col-1 text-nowrap"><label title="Invert match"><input type="checkbox" class="form-check-input meta-neg" id="meta-translator-neg">¬</label></div>
		</div>
		<div class="row">
			<div class="col-3"><label class="form-label" for="meta-translated">Translated</label></div>
			<div class="col"><select class="form-select meta-neg" id="meta-translated" data-sattr="translated"><option value=""></option><option value="1">Yes</option><option value="0">No</option></select></div><div class="col-1"></div>
		</div>
	</div>
</div>
</div>
</div>

<div class="row my-3 align-items-start row-cols-auto">
<div class="col-6">
	<div class="input-group">
		<span class="input-group-text" id="lbl_query">Query</span>
		<input type="text" name="q" class="form-control" id="query" aria-describedby="lbl_query" value="<?=$h_query;?>">
	</div>
	<div class="input-group">
		<span class="input-group-text" id="lbl_query2">SQ</span>
		<input type="text" name="q2" class="form-control" id="query2" aria-describedby="lbl_query2" value="<?=$h_query2;?>">
	</div>
</div>
<div class="col">
	<button type="submit" class="btn btn-success" name="s" value="s">Search <i class="bi bi-search"></i></button>
	<button type="button" class="btn btn-white-success btn-outline-success btnRefine">Refine <i class="bi bi-funnel"></i></button>
</div>
</div>

<div class="row my-3 align-items-start row-cols-auto">
<div class="col">
	<div class="form-check">
		<input class="form-check-input" type="checkbox" name="vt" id="verbatim" <?=(empty($_REQUEST['vt']) ? '' : 'checked');?>>
		<label class="form-check-label" for="verbatim">Don't detect CQL vs. plain text</label>
	</div>
	<div class="form-check ms-3">
		<input class="form-check-input" type="checkbox" name="lc" id="icase" <?=(empty($_REQUEST['lc']) ? '' : 'checked');?>>
		<label class="form-check-label" for="icase">Case-insensitive</label>
	</div>
	<div class="form-check ms-3">
		<input class="form-check-input" type="checkbox" name="nd" id="idiac" <?=(empty($_REQUEST['nd']) ? '' : 'checked');?>>
		<label class="form-check-label" for="idiac">Collapse diacritics</label>
	</div>
</div>
<div class="col">
	<div class="form-check">
		<input class="form-check-input" type="checkbox" name="ub" id="unbound" <?=(($_REQUEST['ub'] !== 'on') ? '' : 'checked');?>>
		<label class="form-check-label" for="unbound">Don't wrap <code>(…) within &lt;s/&gt;</code></label>
	</div>
	<button type="button" class="btn btn-white-success btn-outline-success btnShowCorpora mt-3" style="display: none">Show corpora <i class="bi bi-body-text"></i></button>
</div>
</div>

<div class="row align-items-start row-cols-auto" id="corpora">
<?php
	foreach ($GLOBALS['-groups'] as $group => $g) {
		if ($h_language != $group && $h_language != 'mul') {
			continue;
		}
		$total_ws = 0;
		$flag = '<span class="fi fi-'.$g['flag'].'"></span>';
		if (strlen($g['flag']) > 2) {
			$flag = '<span class="fi" style="background-image: url('.$g['flag'].')"></span>';
		}

		echo '<fieldset class="col"><legend>'.$flag.' '.htmlspecialchars($g['name']).'</legend><div class="columns">';
		foreach ($GLOBALS['-corpora'][$group] as $corp => $vis) {
			$db = new \TDC\PDO\SQLite("{$GLOBALS['CORP_ROOT']}/corpora/{$corp}/meta/stats.sqlite", [\PDO::SQLITE_ATTR_OPEN_FLAGS => \PDO::SQLITE_OPEN_READONLY]);
			$ws = intval($db->prepexec("SELECT c_words + c_numbers + c_alnums as cnt FROM counts WHERE c_which='total'")->fetchAll()[0]['cnt']);
			$checked = '';
			if (!empty($_REQUEST['c'][$corp])) {
				$checked = ' checked';
			}
			$icons = '';
			if ($vis['locked']) {
				$icons .= ' <span class="text-danger" title="Requires password"><i class="bi bi-lock"></i></span>';
			}
			if (intval($db->prepexec("SELECT count(*) as cnt FROM sqlite_schema WHERE name LIKE 'hist_%'")->fetchAll()[0]['cnt'])) {
				$icons .= ' <span class="text-success" title="Histogram available"><i class="bi bi-hourglass"></i></span>';
			}
			if (!empty($vis['group_by'])) {
				$icons .= ' <span class="text-success" title="Group By available"><i class="bi bi-bar-chart-steps"></i></span>';
			}
			if (!empty($vis['features']['sem'])) {
				$icons .= ' <span class="text-warning" title="Has semantic classes"><i class="bi bi-patch-plus"></i></span>';
			}
			echo '<div class="avoid-break"><div class="form-check"><input class="form-check-input chkCorpus" type="checkbox" name="c['.$corp.']" id="chk_'.$corp.'"'.$checked.'><label class="form-check-label" for="chk_'.$corp.'">'.htmlspecialchars($vis['name']).' ('.$icons.' <a href="'.$vis['infolink'].'" target="_blank"><i class="bi bi-info-square"></i></a> <span class="text-muted">'.format_corpsize($ws).' M</span> )</label></div>';
			$total_ws += $ws;

			if (!empty($vis['subs'])) {
				$had_sub = false;
				$subs = '';
				foreach ($vis['subs'] as $sk => $sv) {
					$ws = intval($db->prepexec("SELECT c_words + c_numbers + c_alnums as cnt FROM counts WHERE c_which = ?", [$sk])->fetchAll()[0]['cnt']);
					$checked = '';
					if (!empty($_REQUEST['c'][$corp.'-'.$sk])) {
						$checked = ' checked';
						$had_sub = true;
					}
					// '└' is U+2514 Box Drawings Light Up and Right
					$subs .= '<div>└ <div class="d-inline-block form-check"><input class="form-check-input chkCorpus" type="checkbox" name="c['.$corp.'-'.$sk.']" id="chk_'.$corp.'-'.$sk.'"'.$checked.'><label class="form-check-label" for="chk_'.$corp.'-'.$sk.'">'.htmlspecialchars(strval($sv)).' ('.$icons.' <span class="text-muted">'.format_corpsize($ws).' M</span> )</label></div></div>';
				}

				if (!$had_sub) {
					echo '<div class="collapse show sublist_'.$corp.'">└ <a href=".sublist_'.$corp.'" role="button" data-bs-toggle="collapse" class="ms-3">Show sub-corpora</a></div>';
					echo '<span class="collapse sublist_'.$corp.'">';
					echo $subs;
					echo '</span>';
				}
				else {
					echo $subs;
				}
			}
			echo '</div>';
		}
		echo '</div><div class="my-3"><span class="text-muted">Total words: '.format_corpsize($total_ws).' M</span></div>';
		echo '</fieldset>';
		echo '<div class="w-100"></div>';
	}
?>
<div class="my-3">
	<span class="text-danger"><i class="bi bi-lock"></i></span> Requires password,
	<span class="text-success"><i class="bi bi-bar-chart-steps"></i></span> Group By available,
	<span class="text-warning"><i class="bi bi-patch-plus"></i></span> Semantic classes,
	<span class="text-primary"><i class="bi bi-info-square"></i></span> Corpus information link,
	<span class="text-primary"><i class="bi bi-question-square"></i></span> Help link
</div>
</div>

</form>
<?php
}
?>
</div>

<div class="modal fade" id="modalVector" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h1 class="modal-title fs-5">Vector plot setup</h1>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="row"><div class="col-2">X axis</div><div class="col"><input type="text" class="form-control" id="x1" value="_N"></div><div class="col"><input type="text" class="form-control" id="x2" value="_N"></div></div>
				<div class="row"><div class="col-2">Y axis</div><div class="col"><input type="text" class="form-control" id="y1" value="_ADJ"></div><div class="col"><input type="text" class="form-control" id="y2" value="_ADJ"></div></div>

				<div class="row my-3"><div class="col text-end"><button type="button" class="btn btn-primary btnVectorPlot">Plot</button></div></div>

				<h4 class="my-3">Words to be plotted</h4>
				<span id="vectorWords"></span>
				<form id="vectorForm">
				<div class="row my-3"><div class="col-2">New entry</div><div class="col"><input type="text" class="form-control" id="vectorNew" placeholder="lex_POS"></div><div class="col-1"><button class="btn btn-sm btn-success btnVectorAdd">+</button></div></div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary btnVectorPlot">Plot</button>
			</div>
		</div>
	</div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 m-3" id="toasts">
<?=implode("\n", $toasts);?>
</div>

<script async src="https://www.googletagmanager.com/gtag/js?id=G-4QX6X7X8P8"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-4QX6X7X8P8');
</script>

<script>
  var _paq = window._paq = window._paq || [];
  /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
  (function() {
    var u="//gramtrans.com/matomo/";
    _paq.push(['setTrackerUrl', u+'matomo.php']);
    _paq.push(['setSiteId', '14']);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
  })();
</script>

</body>
</html>
