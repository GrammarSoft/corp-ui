<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>VISL Corpora</title>

	<script src="https://cdn.jsdelivr.net/npm/jquery@3.6/dist/jquery.min.js"></script>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2/dist/js/bootstrap.bundle.min.js"></script>

	<link href="_static/corpus.css?<?=filemtime(__DIR__.'/_static/corpus.css');?>" rel="stylesheet">
	<script src="_static/corpus.js?<?=filemtime(__DIR__.'/_static/corpus.js');?>"></script>
</head>
<body>
<?php
require_once __DIR__.'/_inc/lib.php';

$_REQUEST['c'] = filter_corpora_k($_REQUEST['c'] ?? []);
$_REQUEST['q'] = trim(preg_replace('~[\r\n\t\s\pZ]+~su', ' ', $_REQUEST['q'] ?? ''));

if (!empty($_REQUEST['c']) && !empty($_REQUEST['q'])) {
	$query = '('.$_REQUEST['q'].') within <s/>';
	$focus = 'word';
	$hash = sha256_lc20("{$query} -a {$focus}");
	$folder = '/home/manatee/cache/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2);
	if (!is_dir($folder)) {
		mkdir($folder, 0755, true);
	}
	chdir($folder);

	$s_query = escapeshellarg($query);
	$s_focus = escapeshellarg($focus);

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
	/usr/bin/time -f '%e' -o $hash-$corp.time timeout -k 7m 5m corpquery /home/manatee/registry/$corp $s_query -a $s_focus -c 0 | '{$GLOBALS['CORP_ROOT']}/_bin/query2sqlite' $hash-$corp.sqlite >$hash-$corp.err 2>&1 &
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

	echo '<div class="container-fluid my-3"><div class="row"><div class="col" id="qpages" data-max="0">…</div><div class="col"><select class="form-select form-select-sm" id="qpagesize"><option value="50">50</option><option value="100">100</option><option value="200">200</option><option value="300">300</option><option value="400">400</option><option value="500">500</option></select></div></div><div class="row align-items-start row-cols-auto">';
	foreach ($_REQUEST['c'] as $corp => $_) {
		echo '<div class="col qresults" id="'.htmlspecialchars($corp).'"><div class="qhead text-center fs-5"><span class="qcname fw-bold fs-4">'.htmlspecialchars($corp).'</span><br><span class="qrange">…</span> of <span class="qtotal">…</span></div><div class="qbody">…searching…</div></div>';
	}
	echo '</div></div>';
	echo '<script>let g_hash = "'.$hash.'"; let g_focus = "'.$focus.'";</script>';
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
		<input type="text" name="q" class="form-control" id="query" aria-describedby="lbl_query" value="<?=htmlspecialchars($_REQUEST['q']);?>">
	</div>
</div>
<div class="col">
	<button type="submit" class="btn btn-primary" name="s" value="n">Search</button>
	<!-- <button type="submit" class="btn btn-outline-primary" name="s" value="r">Refine</button> -->
</div>
</div>
</form>
</div>
</body>
</html>
