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

$_REQUEST['b'] = trim($_REQUEST['b'] ?? 'rc');
$_REQUEST['o'] = max(min(intval($_REQUEST['o'] ?? 0), 4), -4);

$toasts = [];

$h_query = '';
$checked = [
	'lc' => '',
	'nd' => '',
	'ha' => '',
	'xe' => '',
	'xs' => '',
	];

$fields = '';
$freq_fields = '';
foreach ($GLOBALS['-fields'] as $k => $v) {
	$sel = '';
	if ($k === $_REQUEST['f']) {
		$sel = ' selected';
	}
	if (substr($k, 0, 2) !== 'h_') {
		$fields .= '<option value="'.$k.'"'.$sel.'>'.htmlspecialchars($v).'</option>'."\n";
	}
	$freq_fields .= '<option value="'.$k.'"'.$sel.'>'.htmlspecialchars($v).'</option>'."\n";
}

if (!empty($_REQUEST['ha'])) {
	$checked['ha'] = 'checked';
}
if (!empty($_REQUEST['xe'])) {
	$checked['xe'] = 'checked';
}
if (!empty($_REQUEST['xs'])) {
	$checked['xs'] = 'checked';
}

$query = $_REQUEST['q'];
$query2 = $_REQUEST['q2'];

if (empty($_REQUEST['vt']) && !empty($query) && !preg_match('~\[.*\]~', $query)) {
	if (!empty($_REQUEST['nd'])) {
		$query = '[word_nd="'.$query.'"]';
	}
	else if (!empty($_REQUEST['lc'])) {
		$query = '[word_lc="'.$query.'"]';
	}
	else {
		$query = '[word="'.$query.'"]';
	}
}
if (empty($_REQUEST['vt']) && !empty($query2) && !preg_match('~\[.*\]~', $query2)) {
	if (!empty($_REQUEST['nd'])) {
		$query2 = '[word_nd="'.$query2.'"]';
	}
	else if (!empty($_REQUEST['lc'])) {
		$query2 = '[word_lc="'.$query2.'"]';
	}
	else {
		$query2 = '[word="'.$query2.'"]';
	}
}

if (preg_match('~\b(\d+):\[.*?\1\.~', $query)) {
	$_REQUEST['ub'] = 0;
}
if (preg_match('~\b(\d+):\[.*?\1\.~', $query2)) {
	$_REQUEST['ub'] = 0;
}

$h_query = htmlspecialchars($query);
$h_query2 = htmlspecialchars($query2);
$h_unbound = '1';
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

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>VISL Corpora</title>

	<script src="https://cdn.jsdelivr.net/npm/jquery@3.6/dist/jquery.min.js"></script>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2/dist/css/bootstrap.min.css">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2/dist/js/bootstrap.bundle.min.js"></script>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10/font/bootstrap-icons.css">
	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.0/dist/chart.umd.js"></script>

	<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@6.6/css/flag-icons.min.css">

	<script>let g_corps = {}; let g_hash = ''; let g_hash_freq = ''; let g_hash_combo = '';</script>
	<link href="_static/refine.css?<?=filemtime(__DIR__.'/_static/refine.css');?>" rel="stylesheet">
	<script src="_static/refine.js?<?=filemtime(__DIR__.'/_static/refine.js');?>"></script>
	<link href="_static/corpus.css?<?=filemtime(__DIR__.'/_static/corpus.css');?>" rel="stylesheet">
	<script src="_static/corpus.js?<?=filemtime(__DIR__.'/_static/corpus.js');?>"></script>
</head>
<body>
<div id="logo" class="container-fluid my-3"><a href="/"><img src="https://corp.hum.sdu.dk/flags/corpuseye-flat-transparent.gif"></a> - <a href="https://corp.hum.sdu.dk/cqp_help.html">Help</a> - <a href="https://visl.sdu.dk/tagset_cg_general.pdf">Taglist</a> (<a href="https://visl.sdu.dk/tagset_cg_all.pdf">unabridged</a>) - <a href="https://www.sketchengine.eu/documentation/corpus-querying/" target="_cql">CQL Documentation</a></div>

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
	$hash = sha256_lc20($query);
	$hash_freq = '';
	$hash_combo = '';
	$folder = $GLOBALS['CORP_ROOT'].'/cache/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2);
	if (!is_dir($folder)) {
		mkdir($folder, 0755, true);
	}
	chdir($folder);

	$s_query = escapeshellarg($query);
	$h_corps = '';
	foreach ($_REQUEST['c'] as $corp => $_) {
		$h_corps .= '<input type="hidden" name="c['.htmlspecialchars($corp).']" value="1">';
	}

	$has_hist = false;
	$corps = [];
	foreach ($_REQUEST['c'] as $corp => $_) {
		[$s_corp,$subc] = explode('-', $corp.'-');
		$db = new \TDC\PDO\SQLite("{$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/meta/stats.sqlite", [\PDO::SQLITE_ATTR_OPEN_FLAGS => \PDO::SQLITE_OPEN_READONLY]);
		$corps[$s_corp] = [
			'db' => $db,
			'hist' => false,
			];
		if (intval($db->prepexec("SELECT count(*) as cnt FROM sqlite_schema WHERE name LIKE 'hist_%'")->fetchAll()[0]['cnt'])) {
			$corps[$s_corp]['hist'] = true;
			$has_hist = true;
		}
	}

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

		$which = $field;

		$nd = $field;
		$coll = '';
		if (!empty($_REQUEST['nd'])) {
			$checked['nd'] = 'checked';
			$checked['lc'] = 'checked';
			$_REQUEST['lc'] = '1';
			$coll = " | '{$GLOBALS['WEB_ROOT']}/_bin/conv-lc-nd'";
			$nd .= '_nd';
			$which .= '/i';
		}
		else if (!empty($_REQUEST['lc'])) {
			$checked['lc'] = 'checked';
			$coll = " | uconv -x any-nfc | uconv -x any-lower";
			$nd .= '_lc';
			$which .= '/i';
		}
		$s_nd = escapeshellarg($nd);

		$which .= ' '.$offset;
		if ($by === 're') {
			$which .= '>0';
		}
		$s_which = escapeshellarg($which);

		$hash_freq = substr(sha256_lc20($which.$coll), 0, 8);

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
	/usr/bin/time -f '%e' -o $dbname.time timeout -k 7m 5m freqs '{$GLOBALS['CORP_ROOT']}/registry/$s_corp' $s_query $s_which 0 $subc $coll | '{$GLOBALS['WEB_ROOT']}/_bin/freq2sqlite' $dbname.sqlite $corp $s_nd >$dbname.err 2>&1 &
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
	/usr/bin/time -f '%e' -o $hash-$corp.hist.time timeout -k 7m 5m '{$GLOBALS['WEB_ROOT']}/_bin/corpquery-histogram' '{$GLOBALS['CORP_ROOT']}/registry/$s_corp' $s_query -c 0 $subc | '{$GLOBALS['WEB_ROOT']}/_bin/histogram' $hash-$corp.hist.sqlite >$hash-$corp.hist.err 2>&1 &
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

	// Sidebar
	echo '<div class="container-fluid my-3"><div class="row flex-nowrap align-items-start"><div class="col sidebar">';
	// Frequency
	echo <<<XHTML
<div class="card bg-lightblue mb-3">
<div class="card-header text-center fw-bold fs-6">
Frequency <i class="bi bi-sort-down"></i>
</div>
<div class="card-body">
<form method="GET">
<input type="hidden" name="l" value="{$h_language}">
<input type="hidden" name="q" value="{$h_query}">
<input type="hidden" name="ub" value="{$h_unbound}">
{$h_corps}
<div class="text-center">
<button class="btn btn-sm btn-success mb-1" type="submit" name="s" value="abc" title="Sort alphabetically">Sort</button>
<button class="btn btn-sm btn-success mb-1" type="submit" name="s" value="freq" title="Sort by absolute frequency">Freq</button>
<br>
<button class="btn btn-sm btn-success btnRel" type="submit" name="s" value="relg" title="Sort by relative frequency (global)" disabled>Rel G</button>
<button class="btn btn-sm btn-success btnRel" type="submit" name="s" value="relc" title="Sort by relative frequency (corpus)" disabled>Rel C</button>
<button class="btn btn-sm btn-success btnRel" id="btnRelS" type="submit" name="s" value="rels" title="Sort by relative frequency (sub-corpus)" disabled>Rel S</button>
</div>
<div class="my-3">
<label class="form-label" for="freq_field">Field</label>
<a tabindex="0" role="button" class="float-right" data-bs-toggle="popover" data-bs-container="body" data-bs-content="The field to focus statistical analysis on"><i class="bi bi-question-square"></i></a>
<select class="form-select" name="f" id="freq_field">
	{$freq_fields}
</select>
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
<div class="my-3 form-check">
<input class="form-check-input" type="checkbox" name="lc" id="lc" {$checked['lc']}>
<label class="form-check-label" for="lc">Collapse case</label>
</div>
<div class="my-3 form-check">
<input class="form-check-input" type="checkbox" name="nd" id="nd" {$checked['nd']}>
<label class="form-check-label" for="nd">Collapse diacritics</label>
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
</form>
</div></div>

XHTML;
	}

	// Page size & Focus field
	echo <<<XHTML
<div class="card bg-lightblue mb-3">
<div class="card-header text-center fw-bold fs-6">
Other <i class="bi bi-sliders"></i>
</div>
<div class="card-body">
<div class="mb-3"><label for="qpagesize" class="form-label">Page size</label><select class="form-select" id="qpagesize"><option value="50">50</option><option value="100">100</option><option value="200">200</option><option value="300">300</option><option value="400">400</option><option value="500">500</option></select></div>
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
	echo '<div class="row"><div class="col qpages">…</div><div class="col"><button class="btn btn-outline-primary my-1 btnShowSearch">Show search <i class="bi bi-search"></i></button></div></div>';
	echo '<div class="row align-items-start" id="search-holder" style="display: none"></div>';
	echo '<div class="row align-items-start row-cols-auto">';
	if ($_REQUEST['s'] === 's') {
		foreach ($_REQUEST['c'] as $corp => $_) {
			echo '<div class="col qresults" id="'.htmlspecialchars($corp).'"><div class="qhead text-center fs-5"><span class="qcname fw-bold fs-4">'.htmlspecialchars($corp).'</span><br><span class="qrange">…</span> of <span class="qtotal">…</span></div><div class="qbody">…searching…</div></div>';
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
			echo '<div class="col qfreqs" id="'.$corp.'"><div class="d-flex">
			<div class="col qhead text-center fs-5"><span class="qcname fw-bold fs-4">'.htmlspecialchars($cname).'</span><br><span class="qrange">…</span> of <span class="qtotal">…</span></div><div class="col text-end qtsv">…</div></div><div class="qbody">…searching…</div></div>';
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
			echo '<div class="col qhist" id="'.htmlspecialchars($corp).'"><div class="qhead text-center fs-5"><span class="qcname fw-bold fs-4">'.htmlspecialchars($corp).'</span><div class="qbody">…searching…</div></div></div>';
		}
	}
	echo '</div>';
	echo '<div class="row"><div class="col qpages">…</div></div>';
	echo '</div>';
	echo '</div></div></div>';
	echo '<script>g_corps = '.json_encode_vb($_REQUEST['c']).'; g_hash = "'.$hash.'"; g_hash_freq = "'.$hash_freq.'"; g_hash_combo = "'.$hash_combo.'";</script>';
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
<td class="center"><button type="button" onclick="refine.toggle_dependency(this);">Dep Head</button> &nbsp; <span class="btnSibling hidden"><button type="button" onclick="refine.toggle_sibling(this);">Sibling</button> &nbsp; </span><button type="button" onclick="refine.delete_table(this);">Delete</button></td>
</tr>
</table>

</div>

<div id="rs2" class="rs">
</div>

<div class="text-center">
<button type="button" id="toggle_sq">Toggle Sub-Query</button>
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
		<input class="form-check-input" type="checkbox" name="ub" id="unbound" <?=(empty($_REQUEST['ub']) ? '' : 'checked');?>>
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
			echo '<div class="avoid-break"><div class="form-check"><input class="form-check-input chkCorpus" type="checkbox" name="c['.$corp.']" id="chk_'.$corp.'"'.$checked.'><label class="form-check-label" for="chk_'.$corp.'">'.htmlspecialchars($vis['name']).' ('.$icons.' <a href="'.$vis['infolink'].'" target="_blank"><i class="bi bi-info-square"></i></a> <span class="text-muted">'.format_corpsize($ws).' M</span> )</label></div>';
			$total_ws += $ws;

			if (!empty($vis['subs'])) foreach ($vis['subs'] as $sk => $sv) {
				$ws = intval($db->prepexec("SELECT c_words + c_numbers + c_alnums as cnt FROM counts WHERE c_which = ?", [$sk])->fetchAll()[0]['cnt']);
				$checked = '';
				if (!empty($_REQUEST['c'][$corp.'-'.$sk])) {
					$checked = ' checked';
				}
				// '└' is U+2514 Box Drawings Light Up and Right
				echo '<div>└ <div class="d-inline-block form-check"><input class="form-check-input chkCorpus" type="checkbox" name="c['.$corp.'-'.$sk.']" id="chk_'.$corp.'-'.$sk.'"'.$checked.'><label class="form-check-label" for="chk_'.$corp.'-'.$sk.'">'.htmlspecialchars(strval($sv)).' ('.$icons.' <span class="text-muted">'.format_corpsize($ws).' M</span> )</label></div></div>';
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
	<span class="text-success"><i class="bi bi-hourglass"></i></span> Histogram available,
	<span class="text-primary"><i class="bi bi-info-square"></i></span> Corpus information link,
	<span class="text-primary"><i class="bi bi-question-square"></i></span> Help link
</div>
</div>

</form>
<?php
}
?>
</div>

<div class="toast-container position-fixed bottom-0 end-0 m-3" id="toasts">
<?=implode("\n", $toasts);?>
</div>

</body>
</html>
