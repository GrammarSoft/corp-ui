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
