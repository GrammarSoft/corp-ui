<?php
declare(strict_types=1);
require_once __DIR__.'/_inc/lib.php';

$_REQUEST['a'] = trim($_REQUEST['a'] ?? '');

if ($_REQUEST['a'] === 'tsv') {
	header('Content-Type: text/tab-separated-values; charset=UTF-8');
	header('Content-disposition: attachment; filename=export.tsv');

	echo "# Info\tText\n";
	foreach ($_SESSION['exported'] as $txts) {
		echo $txts[0]."\t".$txts[1]."\n";
	}

	die();
}

if ($_REQUEST['a'] === 'clear') {
	unset($_SESSION['exported']);
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Corpus Exports</title>

	<script src="https://cdn.jsdelivr.net/npm/jquery@3.6/dist/jquery.min.js"></script>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2/dist/css/bootstrap.min.css">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2/dist/js/bootstrap.bundle.min.js"></script>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10/font/bootstrap-icons.css">

	<link href="_static/corpus.css?<?=filemtime(__DIR__.'/_static/corpus.css');?>" rel="stylesheet">
</head>
<body>
<div class="container-fluid">
<div class="row my-3">
<div class="col">
<form action="./export.php" method="post" target="_blank">
<button class="btn btn-outline-success" name="a" value="tsv">Download TSV</button>
</form>
</div>
<div class="col text-end">
<form action="./export.php" method="post">
<button class="btn btn-outline-danger" name="a" value="clear">Clear</button>
</form>
</div>
</div>
<div class="row my-3">
<div class="col">
<?php

$_REQUEST['c'] = filter_corpora_k($_REQUEST['c'] ?? []);
$_REQUEST['ids'] = preg_replace('~[^\d,]+~', '', $_REQUEST['ids'] ?? '');

$focus = '';
if (!empty($_REQUEST['c']) && !empty($_REQUEST['ids'])) {
	foreach ($_REQUEST['c'] as $corp => $_) {
		[$s_corp,$subc] = explode('-', $corp.'-');

		if (!$_SESSION['corpora'][$s_corp]) {
			break;
		}

		$_REQUEST['ids'] = explode(',', $_REQUEST['ids']);
		foreach ($_REQUEST['ids'] as $k => $id) {
			if (!empty($_SESSION['exported'][$s_corp][$id])) {
				unset($_REQUEST['ids'][$k]);
			}
		}

		sort($_REQUEST['ids']);
		$_REQUEST['ids'] = array_unique($_REQUEST['ids']);
		$_REQUEST['ids'] = implode('|', $_REQUEST['ids']);

		if (empty($_REQUEST['ids'])) {
			break;
		}

		$s_query = escapeshellarg('<s id="('.$_REQUEST['ids'].')"/> containing []');
		$line = shell_exec("'{$GLOBALS['WEB_ROOT']}/_bin/corpquery-histogram' '{$GLOBALS['CORP_ROOT']}/registry/$s_corp' $s_query");

		$lines = explode("\n", trim($line));
		foreach ($lines as $line) {
			$pcs = explode("\t", trim($line), 3);
			preg_match('~ id="(\d+)"~', $pcs[1], $m);
			$id = intval($m[1]);
			$pcs[1] = str_replace('<s ', '<s c="'.$corp.'" ', $pcs[1]);
			$pcs[2] = trim(str_replace('¤ ', '', str_replace("\t", ' ', trim($pcs[2]))));
			$focus = "$s_corp-$id";
			$_SESSION['exported'][$focus] = [$pcs[1], $pcs[2]];
		}
		break;
	}
}

if (!empty($_SESSION['exported'])) {
	echo '<table class="table table-striped table-hover my-3"><thead><tr><th><i class="bi bi-info-square"></i></th><th>Sentence</th></tr></thead><tbody>'."\n";
	foreach ($_SESSION['exported'] as $id => $txts) {
		echo '<tr id="'.$id.'"><td><a data-bs-toggle="popover" data-bs-placement="bottom" title="'.htmlspecialchars($txts[0]).'"><i class="bi bi-info-square"></i></a></td><td>'.htmlspecialchars($txts[1]).'</td></tr>'."\n";
	}
	echo '</tbody></table>'."\n";
	echo "\n";
}
?>
</div>
</div>
</div>
<script>
let popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
let popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));
<?php
if ($focus) {
	echo 'window.location.hash = "'.$focus.'"; $("#'.$focus.'").focus();';
}
?>
</script>
</body>
</html>
