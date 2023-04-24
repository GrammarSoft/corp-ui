<?php
declare(strict_types=1);
require_once __DIR__.'/_inc/lib.php';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Sentence Details</title>

	<script src="https://cdn.jsdelivr.net/npm/jquery@3.6/dist/jquery.min.js"></script>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2/dist/css/bootstrap.min.css">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2/dist/js/bootstrap.bundle.min.js"></script>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10/font/bootstrap-icons.css">
</head>
<body>
<div class="container-fluid">
<div class="row my-3">
<div class="col">
<?php

$_REQUEST['c'] = filter_corpora_k($_REQUEST['c'] ?? []);
$_REQUEST['id'] = intval($_REQUEST['id'] ?? 0);

if (!empty($_REQUEST['c']) && !empty($_REQUEST['id'])) {
	foreach ($_REQUEST['c'] as $corp => $_) {
		[$s_corp,$subc] = explode('-', $corp.'-');

		if (!$_SESSION['corpora'][$s_corp]) {
			break;
		}

		$s_query = escapeshellarg('<s id="'.$_REQUEST['id'].'"/> containing []');
		$line = shell_exec("'{$GLOBALS['WEB_ROOT']}/_bin/corpquery-histogram' '{$GLOBALS['CORP_ROOT']}/registry/$s_corp' $s_query");

		$pcs = explode("\t", trim($line), 3);
		$pcs[0] = intval($pcs[0]);
		$range = $pcs[0].','.($pcs[0] + substr_count($pcs[2], "\t") + 1);

		$lines = shell_exec("'{$GLOBALS['WEB_ROOT']}/_bin/decodevert-ranges' '{$GLOBALS['CORP_ROOT']}/registry/$s_corp' $range");
		$lines = explode("\n", trim($lines));
		$fields = array_flip(explode("\t", trim($lines[0], "# \t\r\n")));

		array_splice($lines, 0, 3);
		array_pop($lines);

		foreach ($lines as $k => $line) {
			$lines[$k] = explode("\t", $line);

			foreach (['lex_nd', 'lex_lc', 'word_nd', 'word_lc'] as $snip) {
				if (array_key_exists($snip, $fields)) {
					array_pop($lines[$k]);
					unset($fields[$snip]);
				}
			}
		}

		echo '<h4>'.htmlspecialchars($corp).'</h4>'."\n";
		echo htmlspecialchars($pcs[1])."<br>";

		echo '<table class="table table-striped table-hover my-3">';
		foreach ($fields as $f => $i) {
			$class = '';
			if ($f === 'word') {
				$class = 'text-danger';
			}
			else if ($f === 'pos') {
				$class = 'text-primary';
			}
			else if ($f === 'func') {
				$class = 'text-success fw-bold';
			}
			else if ($f === 'role') {
				$class = 'fw-bold';
			}

			echo '<tr><th>'.htmlspecialchars($f).'</th>';
			foreach ($lines as $line) {
				echo '<td class="'.$class.'">'.htmlspecialchars($line[$i]).'</td>';
			}
			echo '</tr>';
		}
		echo '</table>';

		break;
	}
}
?>
</div>
</div>
</div>

</body>
</html>
