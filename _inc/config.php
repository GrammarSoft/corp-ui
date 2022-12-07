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
	/*
	'word_lc' => 'Wordform (lower cased)',
	'word_nd' => 'Wordform (lower cased & transliterated)',
	'lex_lc' => 'Baseform (lower cased)',
	'lex_nd' => 'Baseform (lower cased & transliterated)',
	//*/
	];

$GLOBALS['-groups'] = [
	'dan' => 'Danish',
	'deu' => 'German',
	];

$GLOBALS['-corpora'] = [
	'dan' => [
		'dan_twitter' => [
			'name' => 'Twitter (2008-2022 Aug)',
			'subs' => array_combine(range(2017, 2022), range(2017, 2022)),
			],
		],
	'deu' => [
		'deu_twitter_2008_2017' => [
			'name' => 'Twitter (2008-2017)',
			'subs' => array_combine(range(2016, 2017), range(2016, 2017)),
			],
	/*
		'deu_twitter_2018' => [
			'name' => 'Twitter (2018)',
			],
		'deu_twitter_2019' => [
			'name' => 'Twitter (2019)',
			],
		'deu_twitter_2020' => [
			'name' => 'Twitter (2020)',
			],
		'deu_twitter_2021' => [
			'name' => 'Twitter (2021)',
			],
		'deu_twitter_2022' => [
			'name' => 'Twitter (2022 Jan-Aug)',
			],
	//*/
		],
	];
