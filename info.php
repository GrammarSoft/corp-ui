<?php
declare(strict_types=1);
require_once __DIR__.'/_inc/lib.php';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Sentence Details</title>

	<script src="https://cdn.jsdelivr.net/npm/jquery@3.7/dist/jquery.min.js"></script>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/css/bootstrap.min.css">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3/dist/js/bootstrap.bundle.min.js"></script>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11/font/bootstrap-icons.css">

	<link href="_static/corpus.css?<?=filemtime(__DIR__.'/_static/corpus.css');?>" rel="stylesheet">
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

		$ids = range(max(1,$_REQUEST['id']-5), $_REQUEST['id']+5);

		$get = $_GET;
		$line = '';
		$line_t = '';
		$before = [];
		$after = [];
		$s_query = escapeshellarg('<s id="('.implode('|', $ids).')"/> containing []');
		$context = shell_exec("'{$GLOBALS['WEB_ROOT']}/_bin/corpquery-histogram' '{$GLOBALS['CORP_ROOT']}/registry/$s_corp' $s_query");
		foreach (explode("\n", trim($context)) as $c) {
			$t = false;
			if (strpos($c, '<s id="'.$_REQUEST['id'].'"') !== false) {
				$line = $c;
				$t = true;
			}

			preg_match('~<s id="(\d+)"~', $c, $m);
			$c = explode("\t", htmlspecialchars($c), 3);
			array_shift($c);
			$get['id'] = $m[1];
			$c[0] = '<span title="'.$c[0].'"><a href="./info.php?'.http_build_query($get).'">'.$m[1].' <i class="bi bi-info-square"></i></a>';
			$c[1] = str_replace("\t", ' ', $c[1]);
			$c = implode(' ', $c).'</span>';
			if ($t) {
				$line_t = $c;
			}
			else if ($line) {
				$after[] = $c;
			}
			else {
				$before[] = $c;
			}
		}

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

			foreach (['lex_nd', 'lex_lc', 'word_nd', 'word_lc', 'sem', 'h_sem'] as $snip) {
				if (array_key_exists($snip, $fields)) {
					array_pop($lines[$k]);
					unset($fields[$snip]);
				}
			}
		}

		echo '<h4>'.htmlspecialchars($corp).'</h4>'."\n";
		if (!empty($before) || !empty($after)) {
			echo '<h5>Context</h5>';
		}
		if (!empty($before)) {
			echo implode('<br>', $before).'<br>';
		}
		echo '<b>'.$line_t."</b><br>";

		if (!empty($after)) {
			echo implode('<br>', $after);
		}

		echo '<h5 class="mt-3">Analysis</h5>';
		echo '<b>'.htmlspecialchars($pcs[1])."</b><br>";
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
