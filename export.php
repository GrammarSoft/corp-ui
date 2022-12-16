<?php
declare(strict_types=1);
require_once __DIR__.'/_inc/lib.php';

session();

$_REQUEST['a'] = trim($_REQUEST['a'] ?? '');

if ($_REQUEST['a'] === 'tsv') {
	header('Content-Type: text/tab-separated-values; charset=UTF-8');
	header('Content-disposition: attachment; filename=export.tsv');

	echo "# Corpus\n";
	echo "# Info\tText\n";
	foreach ($_SESSION['exported'] as $corp => $txts) {
		echo '# '.$corp."\n";
		foreach ($txts as $id => $txt) {
			echo $txt[0]."\t".$txt[1]."\n";
		}
		echo "\n";
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
$_REQUEST['id'] = intval($_REQUEST['id'] ?? 0);

if (!empty($_REQUEST['c']) && !empty($_REQUEST['id'])) {
	foreach ($_REQUEST['c'] as $corp => $_) {
		[$s_corp,$subc] = explode('-', $corp.'-');

		if (!empty($_SESSION['exported'][$s_corp][$_REQUEST['id']])) {
			break;
		}

		$s_query = escapeshellarg('<s id="'.$_REQUEST['id'].'"/> containing []');
		$line = shell_exec("'{$GLOBALS['WEB_ROOT']}/_bin/corpquery-histogram' '{$GLOBALS['CORP_ROOT']}/registry/$s_corp' $s_query");

		$pcs = explode("\t", trim($line), 3);
		$pcs[2] = trim(str_replace('¤ ', '', str_replace("\t", ' ', trim($pcs[2]))));
		$_SESSION['exported'][$s_corp][$_REQUEST['id']] = [$pcs[1], $pcs[2]];
		ksort($_SESSION['exported'][$s_corp]);
		ksort($_SESSION['exported']);
		break;
	}
}

if (!empty($_SESSION['exported'])) {
	foreach ($_SESSION['exported'] as $corp => $txts) {
		echo '<h4>'.htmlspecialchars($corp).'</h4>'."\n";
		echo '<table class="table table-striped table-hover my-3"><thead><tr><th><i class="bi bi-info-square"></i></th><th>Sentence</th></tr></thead><tbody>'."\n";
		foreach ($txts as $id => $txt) {
			echo '<tr><td><a data-bs-toggle="popover" title="'.htmlspecialchars($txt[0]).'"><i class="bi bi-info-square"></i></a></td><td>'.htmlspecialchars($txt[1]).'</td></tr>'."\n";
		}
		echo '</tbody></table>'."\n";
		echo "\n";
	}
}
?>
</div>
</div>
</div>
<script>
let popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
let popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));
</script>
</body>
</html>
