<?php

declare(strict_types=1);
require_once __DIR__.'/../_vendor/autoload.php';
require_once __DIR__.'/config.php';
require_once __DIR__.'/session.php';

$GLOBALS['-corplist'] = [];
foreach ($GLOBALS['-corpora'] as $g => $cs) {
	foreach ($cs as $corp => $data) {
		$GLOBALS['-corplist'][$corp] =& $GLOBALS['-corpora'][$g][$corp];
		$GLOBALS['-corplist'][$corp]['percent_combo'] = ($GLOBALS['-corplist'][$corp]['percent_combo'] ?? 0.001);
		$GLOBALS['-corplist'][$corp]['infolink'] = ($GLOBALS['-corplist'][$corp]['infolink'] ?? "https://corp.visl.dk/copyright.html#{$corp}");
		$GLOBALS['-corplist'][$corp]['features'] = ($GLOBALS['-corplist'][$corp]['features'] ?? []);
		$GLOBALS['-corplist'][$corp]['group_by'] = ($GLOBALS['-corplist'][$corp]['group_by'] ?? null);
		$GLOBALS['-corplist'][$corp]['word2vec'] = ($GLOBALS['-corplist'][$corp]['word2vec'] ?? null);
	}
}

$GLOBALS['-context-max'] = 150;

putenv('LC_ALL=C.UTF-8');
setlocale(LC_ALL, 'C.UTF-8');
$GLOBALS['WEB_ROOT'] = dirname(__DIR__);
$GLOBALS['CORP_ROOT'] = dirname($GLOBALS['WEB_ROOT']).'/storage';
$_REQUEST = array_merge($_GET, $_POST);

foreach ($GLOBALS['-corplist'] as $corp => $_) {
	if (file_exists("{$GLOBALS['CORP_ROOT']}/corpora/{$corp}/sem.fsa")) {
		$GLOBALS['-corplist'][$corp]['features']['sem'] = true;
	}
}

$GLOBALS['-value-class'] = [
	'word' => '~^[\pL][- \'`´\pL\pM]*$~u',
	'number' => '~^[\pN\d][- \pN\d]*$~u',
	'alnum' => '~^[\pL\pN\d][- \pL\pM\pN\d]*$~u',
	'punct' => '~^[\pP,.:;!]+$~u',
	'emoji' => '~^emo-~u',
	'other' => '',
	];

if (PHP_SAPI !== 'cli') {
	session();
}

require_once __DIR__.'/auth-base.php';
if (file_exists(__DIR__.'/auth-impl.php')) {
	require_once __DIR__.'/auth-impl.php';
	$GLOBALS['-auth'] = new \AuthImpl;
}
else {
	$GLOBALS['-auth'] = new \AuthBase;
}
$GLOBALS['-auth']->lock();

function maybe_export_config() {
	if (!file_exists(__DIR__.'/../_static/config.js') || filemtime(__DIR__.'/config.php') > filemtime(__DIR__.'/../_static/config.js')) {
		$conf = "'use strict';\n\nlet g_config = {\n\tfields: ";
		$conf .= json_encode_vb($GLOBALS['-fields']);
		$conf .= ",\n\tscale: ";
		$conf .= json_encode_vb($GLOBALS['-scale']);
		$conf .= ",\n\tgroups: ";
		$conf .= json_encode_vb($GLOBALS['-groups']);
		$conf .= ",\n\tcorpora: ";
		$conf .= json_encode_vb($GLOBALS['-corplist']);
		$attrs = [];
		foreach ($GLOBALS['-corplist'] as $c => $_) {
			$attrs[$c] = [];
			foreach (glob("{$GLOBALS['CORP_ROOT']}/corpora/{$c}/s.*.lex") as $f) {
				if (preg_match('~/s\.([a-z0-9]+)\.lex$~', $f, $m)) {
					$attrs[$c][] = $m[1];
				}
			}
		}
		$conf .= ",\n\tattrs: ";
		$conf .= json_encode_vb($attrs);
		$conf .= "\n}\n";
		file_put_contents(__DIR__.'/../_static/config.js', $conf);
	}
}

function b64_slug($rv): string {
	$rv = base64_encode($rv);
	$rv = trim($rv, '=');
	$rv = str_replace('+', 'z', $rv);
	$rv = str_replace('/', 'Z', $rv);
	$rv = preg_replace('~^\d~', 'n', $rv);
	return $rv;
}

function sha256_lc20($in): string {
	return strtolower(substr(b64_slug(hash('sha256', $in, true)), 0, 20));
}

function json_encode_vb($v, $o=0): string {
	return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | $o);
}

function filter_corpora_k($arr): array {
	foreach ($arr as $k => $v) {
		$sub = explode('-', $k.'-');
		if (empty($GLOBALS['-corplist'][$sub[0]])) {
			unset($arr[$k]);
		}
		if (!empty($sub[1]) && (empty($GLOBALS['-corplist'][$sub[0]]['subs']) || !array_key_exists($sub[1], $GLOBALS['-corplist'][$sub[0]]['subs']))) {
			unset($arr[$k]);
		}
	}
	ksort($arr);
	return $arr;
}

function filter_corpora_v($arr): array {
	sort($arr);
	$arr = array_unique($arr);
	foreach ($arr as $k => $v) {
		$sub = explode('-', $v.'-');
		if (empty($GLOBALS['-corplist'][$sub[0]])) {
			unset($arr[$k]);
		}
		if (!empty($sub[1]) && (empty($GLOBALS['-corplist'][$sub[0]]['subs']) || !array_key_exists($sub[1], $GLOBALS['-corplist'][$sub[0]]['subs']))) {
			unset($arr[$k]);
		}
	}
	return array_values($arr);
}

function format_corpsize($ws): string {
	if ($ws < 10000000) {
		return number_format($ws/1000000.0, 2, '.', '');
	}
	return number_format($ws/1000000.0, 1, '.', '');
}

function parse_fields(&$dest, $src) {
	$fld = null;
	while (preg_match('~^([a-z_]+)(!?)=(=?)~', $src, $fld)) {
		$not = $fld[2] ? true : false;
		$vbtm = $fld[2] ? true : false;
		$fld = $fld[1];
		$src = mb_substr($src, mb_strlen($fld) + $not*1 + $vbtm*1 + 1);

		$val = null;
		if (!preg_match('~"([^"]+)"~', $src, $val)) {
			break;
		}

		$src = trim(mb_substr($src, mb_strlen($val[0])));
		$dest[] = ['k' => $fld, 'i' => $not, 'r' => $vbtm, 'v' => $val[0]];

		if (mb_substr($src, 0, 2) === '& ') {
			$src = trim(mb_substr($src, 2));
		}
	}

	return $src;
}

function parse_query($src) {
	$rv = [
		'tokens' => [],
		'quants' => [],
		'meta' => [],
		'begin' => null,
		'end' => null,
	];

	$src = str_replace('_PLUS_', '+', $src);
	$src = str_replace('_HASH_', '#', $src);
	$src = str_replace('_AND_', '&', $src);
	$src = str_replace('_PCNT_', '%', $src);
	$src = trim($src);

	$meta = null;
	while (preg_match('~^\((.+)\) within <s (.+?)\/>$~', $src, $meta)) {
		$src = $meta[1];
		$meta = $meta[2];

		parse_fields($rv['meta'], $meta);
	}

	$s_begin = null;
	while (preg_match('~^<s\b ?(.+?)> ?(?:\[word="¤"\]\*)?(.*)$~', $src, $s_begin)) {
		$src = trim($s_begin[2]);
		$s_begin = $s_begin[1];

		$rv['begin'] = [];
		parse_fields($rv['begin'], $s_begin);
	}

	$s_end = null;
	while (preg_match('~^(.*) <\/s\b ?(.+?)>$~', $src, $s_end)) {
		$src = trim($s_end[1]);
		$s_end = $s_end[2];

		$rv['end'] = [];
		parse_fields($rv['end'], $s_end);
	}

	if (mb_substr($src, 0, 1) !== '[') {
		$src = '['.$src.']';
	}

	while (mb_substr($src, 0, 1) === '[') {
		$token = [];
		$src = mb_substr($src, 1);

		$src = parse_fields($token, $meta);

		if (mb_substr($src, 0, 1) === ']') {
			$src = trim(mb_substr($src, 1));
		}
		if (mb_strlen($src) && mb_substr($src, 0, 1) != '[') {
			$rv['quants'] = mb_substr($src, 0, 1);
			$src = trim(mb_substr($src, 1));
		}
		else {
			$rv['quants'][] = '';
		}

		$rv['tokens'][] = $token;
	}

	return $rv;
}

function render_field($field) {
	$rv = $field['k'];
	if ($field['i']) {
		$rv .= '!';
	}
	$rv .= '=';
	if ($field['r']) {
		$rv .= '=';
	}
	$rv .= $field['v'];
	$rv .= ' & ';
	return $rv;
}

function render_query($q) {
	$rv = '';
	for ($i=0, $ie=count($q['tokens']) ; $i<$ie ; ++$i) {
		$fields = $q['tokens'][$i];
		$rv .= '[';
		foreach ($fields as $f) {
			$rv .= render_field($f);
		}
		$rv = preg_replace('~ & $~', '', $rv);
		$rv .= ']';
		$rv .= $q['quants'][$i];
		$rv .= ' ';
	}
	$rv = trim($rv);

	if (is_array($q['begin'])) {
		$s = '<s';
		if (count($q['begin'])) {
			$s .= ' ';
			foreach ($q['begin'] as $f) {
				$s .= render_field($f);
			}
			$s = preg_replace('~ & $~', '', $s);
		}
		$rv = $s . '> [word="¤"]* ' . $rv;
	}

	if (is_array($q['end'])) {
		$rv .= ' </s';
		if (count($q['end'])) {
			$rv .= ' ';
			foreach ($q['end'] as $f) {
				$rv .= render_field($f);
			}
			$rv = preg_replace('~ & $~', '', $rv);
		}
		$rv .= '>';
	}

	if (!empty($q['meta'])) {
		$rv = '(' . $rv . ') within <s ';
		foreach ($q['meta'] as $f) {
			$rv .= render_field($f);
		}
		$rv = preg_replace('~ & $~', '', $rv);
		$rv .= '/>';
	}

	return $rv;
}

function siblingify($q, $off=0) {
	$q = parse_query($q);

	$rv = '';
	$sc = '';
	$cond = '';
	for ($i=0, $ie=count($q['tokens']) ; $i<$ie ; ++$i) {
		$fields = $q['tokens'][$i];
		$has_sib = false;
		for ($j=0, $je=count($fields) ; $j<$je ; ++$j) {
			if (mb_substr($fields[$j]['k'], 0, 2) === 's_') {
				$has_sib = true;
			}
		}

		if ($has_sib) {
			$sc .= 'containing '.($i+$ie+$off+1).':[';
			$cond .= ' & '.($i+$off+1).'.dparent='.($i+$ie+$off+1).'.dparent';
			for ($j=0, $je=count($fields) ; $j<$je ; ++$j) {
				$field = $fields[$j];
				if (mb_substr($fields[$j]['k'], 0, 2) === 's_') {
					$sc .= mb_substr($field['k'], 2);
					if ($field['i']) {
						$sc .= '!';
					}
					$sc .= '=';
					if ($field['r']) {
						$sc .= '=';
					}
					$sc .= $field['v'];
					$sc .= ' & ';
					unset($fields[$j]);
				}
			}
			$sc = preg_replace('~ & $~', '', $sc);
			$sc .= ']';
			$sc .= ' ';

			$fields = array_values($fields);
			$rv .= ($i+$off+1).':';
		}

		$rv .= '[';
		for ($j=0, $je=count($fields) ; $j<$je ; ++$j) {
			$field = $fields[$j];
			$rv .= $field['k'];
			if ($field['i']) {
				$rv .= '!';
			}
			$rv .= '=';
			if ($field['r']) {
				$rv .= '=';
			}
			$rv .= $field['v'];
			$rv .= ' & ';
		}
		$rv = preg_replace('~ & $~', '', $rv);
		$rv .= ']';
		$rv .= $q['quants'][$i];
		$rv .= ' ';
	}
	$sc = trim($sc);
	$cond = trim($cond);
	$rv = trim($rv);

	if (!empty($q['meta'])) {
		$rv = '(' . $rv . ') within <s ';
		for ($j=0, $je=count($q['meta']) ; $j<$je ; ++$j) {
			$field = $q['meta'][$j];
			$rv .= $field['k'];
			if ($field['i']) {
				$rv .= '!';
			}
			$rv .= '=';
			if (!$field['r']) {
				$rv .= '=';
			}
			$rv .= $field['v'];
			$rv .= ' & ';
		}
		$rv = preg_replace('~ & $~', '', $rv);
		$rv .= '/>';
	}

	return "(({$rv}) within (<s/> {$sc})) {$cond}";
}
