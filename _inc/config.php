<?php

putenv('PYTHONPATH=/usr/local/manatee/lib/python3.10/site-packages');
putenv('PATH='.getenv('PATH').':/usr/local/manatee/bin');

$GLOBALS['-fields'] = [
	'word' => 'Wordform / surface form',
	'lex' => 'Baseform / lemma',
	'extra' => 'Extra',
	'pos' => 'Part of speech',
	'morph' => 'Morphology',
	'func' => 'Syntactic function',
	'role' => 'Semantic role',
	'dself' => 'Dependency ID',
	'dparent' => 'Dependency parent ID',
	'word_lc' => 'Wordform (lower cased)',
	'word_nd' => 'Wordform (lower cased & transliterated)',
	'lex_lc' => 'Baseform (lower cased)',
	'lex_nd' => 'Baseform (lower cased & transliterated)',
	];

$GLOBALS['-groups'] = [
	'dan' => 'Danish',
	'deu' => 'German',
	];

$GLOBALS['-corpora'] = [
	'dan' => [
		'dan_twitter' => 'Twitter',
		],
	'deu' => [
		'deu_twitter_2008_2017' => 'Twitter (2008-2017)',
		'deu_twitter_2018' => 'Twitter (2018)',
		'deu_twitter_2019' => 'Twitter (2019)',
		'deu_twitter_2020' => 'Twitter (2020)',
		'deu_twitter_2021' => 'Twitter (2021)',
		'deu_twitter_2022' => 'Twitter (2022 Jan-Aug)',
		],
	];
