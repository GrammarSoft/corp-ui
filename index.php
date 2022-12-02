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
		$_REQUEST['ct'] = 0;
	}

	$h_query = htmlspecialchars($query);
	$h_constrain = '';
	if (!empty($_REQUEST['ct'])) {
		$h_constrain = '1';
		$query = '('.$query.') within <s/>';
	}
	$field = $_REQUEST['f'];
	$hash = sha256_lc20("{$query} -d {$field}");
	$hash_freq = '';
	$folder = $GLOBALS['CORP_ROOT'].'/cache/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2);
	if (!is_dir($folder)) {
		mkdir($folder, 0755, true);
	}
	chdir($folder);

	$s_query = escapeshellarg($query);
	$s_field = escapeshellarg($field);
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
			if (!file_exists("$hash-$corp.sqlite") || !filesize("$hash-$corp.sqlite")) {
				$exec = true;
			}

			$sh .= <<<XSH

if [ ! -s '$hash-$corp.sqlite' ]; then
	/usr/bin/time -f '%e' -o $hash-$corp.time timeout -k 7m 5m corpquery '{$GLOBALS['CORP_ROOT']}/registry/$corp' $s_query -d $s_field -a $s_field -c 0 | '{$GLOBALS['WEB_ROOT']}/_bin/query2sqlite' $hash-$corp.sqlite >$hash-$corp.err 2>&1 &
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
	else if ($_REQUEST['s'] === 'abc' || $_REQUEST['s'] === 'freq' || $_REQUEST['s'] === 'relfreq') {
		$offset = $_REQUEST['o'];
		$by = $_REQUEST['b'];
		if ($by === 'lc') {
			$offset -= 1;
			$by = 'le';
		}
		else if ($by === 'rc') {
			$offset += 1;
			$by = 're';
		}

		$which = $field.' '.$offset;
		if ($by === 're') {
			$which .= '>0';
		}
		$s_which = escapeshellarg($which);
		$hash_freq = substr(sha256_lc20($which), 0, 8);

		$sh = <<<XSH
#!/bin/bash
set -e
cd '$folder'

XSH;

		$exec = false;
		foreach ($_REQUEST['c'] as $corp => $_) {
			if (!file_exists("$hash-$corp.freq-$hash_freq.sqlite") || !filesize("$hash-$corp.freq-$hash_freq.sqlite")) {
				$exec = true;
			}

			$sh .= <<<XSH

if [ ! -s '$hash-$corp.freq-$hash_freq.sqlite' ]; then
	/usr/bin/time -f '%e' -o $hash-$corp.freq-$hash_freq.time timeout -k 7m 5m freqs '{$GLOBALS['CORP_ROOT']}/registry/$corp' $s_query $s_which | '{$GLOBALS['WEB_ROOT']}/_bin/freq2sqlite' $hash-$corp.freq-$hash_freq.sqlite >$hash-$corp.freq-$hash_freq.err 2>&1 &
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

	echo '<div class="container-fluid my-3"><div class="row align-items-start"><div class="col-2">';
	echo '<div class="card text-bg-light mb-3"><div class="card-body">';
	echo <<<XHTML
<form method="GET">
<input type="hidden" name="q" value="{$h_query}">
<input type="hidden" name="ct" value="{$h_constrain}">
{$h_corps}
<div class="text-center">
<button class="btn btn-sm btn-outline-primary" type="submit" name="s" value="abc" title="Sort alphabetically" disabled>Sort</button>
<button class="btn btn-sm btn-outline-primary" type="submit" name="s" value="freq" title="Sort by absolute frequency">Freq</button>
<button class="btn btn-sm btn-outline-primary" type="submit" name="s" value="relfreq" title="Sort by relative frequency" disabled>Rel</button>
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
</form>

XHTML;
	echo '</div></div>';
	echo '<div class="card text-bg-light mb-3">';
	echo '<div class="card-body"><label for="qpagesize" class="form-label fw-bold">Page size</label><select class="form-select" id="qpagesize"><option value="50">50</option><option value="100">100</option><option value="200">200</option><option value="300">300</option><option value="400">400</option><option value="500">500</option></select></div>';
	if ($_REQUEST['s'] === 's') {
		echo '<div class="card-body"><label class="form-label fw-bold" for="qfocus">Focus field</label><select class="form-select" id="qfocus">'.$fields.'</select></div>';
	}
	echo '</div>';
	echo '</div><div class="col-10">';
	echo '<div class="container-fluid my-3">';
	echo '<div class="row"><div class="col qpages">…</div></div>';
	echo '<div class="row align-items-start row-cols-auto">';
	if ($_REQUEST['s'] === 's') {
		foreach ($_REQUEST['c'] as $corp => $_) {
			echo '<div class="col qresults" id="'.htmlspecialchars($corp).'"><div class="qhead text-center fs-5"><span class="qcname fw-bold fs-4">'.htmlspecialchars($corp).'</span><br><span class="qrange">…</span> of <span class="qtotal">…</span></div><div class="qbody">…searching…</div></div>';
		}
	}
	else if ($_REQUEST['s'] === 'abc' || $_REQUEST['s'] === 'freq' || $_REQUEST['s'] === 'relfreq') {
		foreach ($_REQUEST['c'] as $corp => $_) {
			echo '<div class="col qfreqs" id="'.htmlspecialchars($corp).'"><div class="qhead text-center fs-5"><span class="qcname fw-bold fs-4">'.htmlspecialchars($corp).'</span><br><span class="qrange">…</span> of <span class="qtotal">…</span></div><div class="qbody">…searching…</div></div>';
		}
	}
	echo '</div>';
	echo '<div class="row"><div class="col qpages">…</div></div>';
	echo '</div>';
	echo '</div></div></div>';
	echo '<script>let g_hash = "'.$hash.'"; let g_hash_freq = "'.$hash_freq.'";</script>';
}

?>

<div class="container my-5">
<form method="GET">
<div class="row align-items-start row-cols-auto">
<?php
foreach ($GLOBALS['-corpora'] as $group => $cs) {
	echo '<fieldset class="col"><legend>'.htmlspecialchars($GLOBALS['-groups'][$group]).'</legend>';
	foreach ($cs as $corp => $vis) {
		$checked = '';
		if (!empty($_REQUEST['c'][$corp])) {
			$checked = ' checked';
		}
		echo '<div class="form-check"><input class="form-check-input" type="checkbox" name="c['.$corp.']" id="chk_'.$corp.'"'.$checked.'><label class="form-check-label" for="chk_'.$corp.'">'.htmlspecialchars($vis).'</label></div>';
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
		<input class="form-check-input" type="checkbox" name="ct" id="constrain" <?=(empty($_REQUEST['ct']) ? '' : 'checked');?>>
		<label class="form-check-label" for="constrain">Constrain query inside <code>&lt;s&gt;…&lt;/s&gt;</code> regions</label>
	</div>
</div>
</div>
</form>
</div>

</body>
</html>
