<?php
declare(strict_types=1);
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

	<script>let g_hash = ''; let g_hash_freq = '';</script>
	<link href="_static/corpus.css?<?=filemtime(__DIR__.'/_static/corpus.css');?>" rel="stylesheet">
	<script src="_static/corpus.js?<?=filemtime(__DIR__.'/_static/corpus.js');?>"></script>
</head>
<body>
<?php
require_once __DIR__.'/_inc/lib.php';

$_REQUEST['c'] = filter_corpora_k($_REQUEST['c'] ?? []);
$_REQUEST['q'] = trim(preg_replace('~[\r\n\t\s\pZ]+~su', ' ', $_REQUEST['q'] ?? ''));

$_REQUEST['f'] = trim($_REQUEST['f'] ?? 'word');
if (!array_key_exists($_REQUEST['f'], $GLOBALS['-fields'])) {
	$_REQUEST['f'] = 'word';
}

$_REQUEST['b'] = trim($_REQUEST['b'] ?? 'rc');
$_REQUEST['o'] = max(min(intval($_REQUEST['o'] ?? 0), 4), -4);

$h_query = '';
$checked = [
	'lc' => '',
	'nd' => '',
	];

$fields = '';
foreach ($GLOBALS['-fields'] as $k => $v) {
	$sel = '';
	if ($k === $_REQUEST['f']) {
		$sel = ' selected';
	}
	$fields .= '<option value="'.$k.'"'.$sel.'>'.htmlspecialchars($v).'</option>'."\n";
}

if (!empty($_REQUEST['c']) && !empty($_REQUEST['q'])) {
	$query = $_REQUEST['q'];
	if (preg_match('~\b(\d+):\[.*?\1\.~', $query)) {
		$_REQUEST['ub'] = 0;
	}

	$h_query = htmlspecialchars($query);
	$h_unbound = '1';
	if (empty($_REQUEST['ub'])) {
		$h_unbound = '';
		$query = '('.$query.') within <s/>';
	}
	$field = $_REQUEST['f'];
	$hash = sha256_lc20($query);
	$hash_freq = '';
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
		foreach ($_REQUEST['c'] as $corp => $_) {
			[$s_corp,$subc] = explode('-', $corp.'-');
			if (!empty($subc) && preg_match('~^[a-z0-9]+$~', $subc) && file_exists("{$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/subc/{$subc}.subc")) {
				$subc = "{$GLOBALS['CORP_ROOT']}/corpora/{$s_corp}/subc/{$subc}.subc";
			}
			else {
				$subc = '';
			}
			if (!file_exists("$hash-$corp.freq-$hash_freq.sqlite") || !filesize("$hash-$corp.freq-$hash_freq.sqlite")) {
				$exec = true;
			}

			$sh .= <<<XSH

if [ ! -s '$hash-$corp.freq-$hash_freq.sqlite' ]; then
	/usr/bin/time -f '%e' -o $hash-$corp.freq-$hash_freq.time timeout -k 7m 5m freqs '{$GLOBALS['CORP_ROOT']}/registry/$s_corp' $s_query $s_which 0 $subc $coll | '{$GLOBALS['WEB_ROOT']}/_bin/freq2sqlite' $hash-$corp.freq-$hash_freq.sqlite $corp $s_nd >$hash-$corp.freq-$hash_freq.err 2>&1 &
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

	echo '<div class="container-fluid my-3"><div class="row flex-nowrap align-items-start"><div class="col sidebar">';
	echo '<div class="card text-bg-light mb-3"><div class="card-body">';
	echo <<<XHTML
<form method="GET">
<input type="hidden" name="q" value="{$h_query}">
<input type="hidden" name="ub" value="{$h_unbound}">
{$h_corps}
<div class="text-center">
<button class="btn btn-sm btn-outline-primary mb-1" type="submit" name="s" value="abc" title="Sort alphabetically">Sort</button>
<button class="btn btn-sm btn-outline-primary mb-1" type="submit" name="s" value="freq" title="Sort by absolute frequency">Freq</button>
<br>
<button class="btn btn-sm btn-outline-primary btnRel" type="submit" name="s" value="relg" title="Sort by relative frequency (global)" disabled>Rel G</button>
<button class="btn btn-sm btn-outline-primary btnRel" type="submit" name="s" value="relc" title="Sort by relative frequency (corpus)" disabled>Rel C</button>
<button class="btn btn-sm btn-outline-primary btnRel" id="btnRelS" type="submit" name="s" value="rels" title="Sort by relative frequency (sub-corpus)" disabled>Rel S</button>
</div>
<div class="my-3">
<label class="form-label" for="freq_field">Field</label>
<select class="form-select" name="f" id="freq_field">
	{$fields}
	<option>TODO: Head fields</option>
</select>
</div>
<div class="my-3">
<label class="form-label" for="freq_by">By</label>
<select class="form-select" name="b" id="freq_by" size="4">
	{$by_sel}
</select>
</div>
<div class="my-3">
<label class="form-label" for="freq_offset">Offset</label>
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

XHTML;
	echo '</div></div>';
	echo '<div class="card text-bg-light mb-3">';
	echo '<div class="card-body"><div class="mb-3"><label for="qpagesize" class="form-label fw-bold">Page size</label><select class="form-select" id="qpagesize"><option value="50">50</option><option value="100">100</option><option value="200">200</option><option value="300">300</option><option value="400">400</option><option value="500">500</option></select></div>';
	if ($_REQUEST['s'] === 's') {
		echo '<div class="mb-3"><label class="form-label fw-bold" for="qfocus">Focus field</label><select class="form-select" id="qfocus">'.$fields.'</select></div>';
	}
	echo '</div></div>';
	echo '</div><div class="col">';
	echo '<div class="container-fluid my-3">';
	echo '<div class="row"><div class="col qpages">…</div></div>';
	echo '<div class="row align-items-start row-cols-auto">';
	if ($_REQUEST['s'] === 's') {
		foreach ($_REQUEST['c'] as $corp => $_) {
			echo '<div class="col qresults" id="'.htmlspecialchars($corp).'"><div class="qhead text-center fs-5"><span class="qcname fw-bold fs-4">'.htmlspecialchars($corp).'</span><br><span class="qrange">…</span> of <span class="qtotal">…</span></div><div class="qbody">…searching…</div></div>';
		}
	}
	else if ($_REQUEST['s'] === 'abc' || $_REQUEST['s'] === 'freq' || $_REQUEST['s'] === 'relg' || $_REQUEST['s'] === 'relc' || $_REQUEST['s'] === 'rels') {
		foreach ($_REQUEST['c'] as $corp => $_) {
			echo '<div class="col qfreqs" id="'.htmlspecialchars($corp).'"><div class="qhead text-center fs-5"><span class="qcname fw-bold fs-4">'.htmlspecialchars($corp).'</span><br><span class="qrange">…</span> of <span class="qtotal">…</span></div><div class="qbody">…searching…</div></div>';
		}
	}
	echo '</div>';
	echo '<div class="row"><div class="col qpages">…</div></div>';
	echo '</div>';
	echo '</div></div></div>';
	echo '<script>g_hash = "'.$hash.'"; g_hash_freq = "'.$hash_freq.'";</script>';
}

?>

<div class="container my-5">
<form method="GET">
<div class="row align-items-start row-cols-auto">
<?php
foreach ($GLOBALS['-corpora'] as $group => $cs) {
	echo '<fieldset class="col"><legend>'.htmlspecialchars($GLOBALS['-groups'][$group]).'</legend>';
	foreach ($cs as $corp => $vis) {
		$db = new \TDC\PDO\SQLite("{$GLOBALS['CORP_ROOT']}/corpora/{$corp}/meta/stats.sqlite", [\PDO::SQLITE_ATTR_OPEN_FLAGS => \PDO::SQLITE_OPEN_READONLY]);
		$ws = intval($db->prepexec("SELECT c_words + c_numbers + c_alnums as cnt FROM counts WHERE c_which='total'")->fetchAll()[0]['cnt']);
		$checked = '';
		if (!empty($_REQUEST['c'][$corp])) {
			$checked = ' checked';
		}
		echo '<div class="form-check"><input class="form-check-input" type="checkbox" name="c['.$corp.']" id="chk_'.$corp.'"'.$checked.'><label class="form-check-label" for="chk_'.$corp.'">'.htmlspecialchars($vis['name']).' ( <span class="text-muted">'.number_format($ws/1000000.0, 1, '.', '').' M</span> )</label></div>';

		if (!empty($vis['subs'])) foreach ($vis['subs'] as $sk => $sv) {
			$ws = intval($db->prepexec("SELECT c_words + c_numbers + c_alnums as cnt FROM counts WHERE c_which = ?", [$sk])->fetchAll()[0]['cnt']);
			$checked = '';
			if (!empty($_REQUEST['c'][$corp.'-'.$sk])) {
				$checked = ' checked';
			}
			echo '<div class="form-check ms-3"><input class="form-check-input" type="checkbox" name="c['.$corp.'-'.$sk.']" id="chk_'.$corp.'-'.$sk.'"'.$checked.'><label class="form-check-label" for="chk_'.$corp.'-'.$sk.'">'.htmlspecialchars(strval($sv)).' ( <span class="text-muted">'.number_format($ws/1000000.0, 1, '.', '').' M</span> )</label></div>';
		}
	}
	echo '</fieldset>';
}
?>
</div>
<div class="row my-3 align-items-start row-cols-auto">
<div class="col-6">
	<div class="input-group">
		<span class="input-group-text" id="lbl_query">Query</span>
		<input type="text" name="q" class="form-control" id="query" aria-describedby="lbl_query" value="<?=$h_query;?>">
	</div>
</div>
<div class="col">
	<button type="submit" class="btn btn-primary" name="s" value="s">Search</button>
	<!-- <button type="submit" class="btn btn-outline-primary" name="s" value="r">Refine</button> -->
</div>
</div>
<div class="row my-3 align-items-start row-cols-auto">
<div class="col">
	<div class="form-check">
		<input class="form-check-input" type="checkbox" name="ub" id="unbound" <?=(empty($_REQUEST['ub']) ? '' : 'checked');?>>
		<label class="form-check-label" for="unbound">Allow query to exceed <code>&lt;s&gt;…&lt;/s&gt;</code> regions</label>
	</div>
</div>
</div>
</form>
</div>

</body>
</html>
