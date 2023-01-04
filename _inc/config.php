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

	'h_word' => 'Head Wordform / surface form',
	'h_lex' => 'Head Baseform / lemma',
	'h_extra' => 'Head Extra',
	'h_pos' => 'Head Part of speech',
	'h_morph' => 'Head Morphology',
	'h_func' => 'Head Syntactic function',
	'h_role' => 'Head Semantic role',
	'h_dself' => 'Head Dependency ID',
	'h_dparent' => 'Head Dependency parent ID',

	/*
	'word_lc' => 'Wordform (lower cased)',
	'word_nd' => 'Wordform (lower cased & transliterated)',
	'lex_lc' => 'Baseform (lower cased)',
	'lex_nd' => 'Baseform (lower cased & transliterated)',
	//*/
	];

$GLOBALS['-scale'] = floatval(100000.0);

$GLOBALS['-groups'] = [
	'dan' => 'Danish',
	'eng' => 'English',
	'epo' => 'Esperanto',
	'fao' => 'Faroese',
	'fra' => 'French',
	'deu' => 'German',
	'ice' => 'Icelandic',
	'ita' => 'Italian',
	'nor' => 'Norwegian',
	'por' => 'Portuguese',
	'ron' => 'Romanian',
	'spa' => 'Spanish',
	'swe' => 'Swedish',
	];

$GLOBALS['-corpora'] = [
	'dan' => [
		'dan_barelgazel' => [
			'name' => 'dfk-barelgazel',
			],
		'dan_c90' => [
			'name' => 'Korpus90',
			'percent_combo' => 7.5,
			],
		'dan_c2000' => [
			'name' => 'Korpus2000',
			'percent_combo' => 7.5,
			],
		'dan_kdk2010_dep' => [
			'name' => 'Korpus2010',
			'percent_combo' => 15,
			],
		'dan_correct' => [
			'name' => 'Corrected K90/2000',
			'percent_combo' => 0,
			],
		'dan_catma' => [
			'name' => 'Stereotype Interviews',
			],
		'dan_dfk_pol' => [
			'name' => 'dfk-folketing',
			],
		'dan_europarl' => [
			'name' => 'Europarl',
			'percent_combo' => 10,
			],
		'dan_facebook' => [
			'name' => 'Facebook',
			],
		'dan_facebook_minority' => [
			'name' => 'FBmin v.1',
			'percent_combo' => 0,
			],
		'dan_facebook_minority_20180710' => [
			'name' => 'FBmin v.2',
			'percent_combo' => 0,
			],
		'dan_facebook_minority_avis_20180710' => [
			'name' => 'FBmin news',
			'percent_combo' => 0,
			],
		'dan_facebook_minority_dr' => [
			'name' => 'FBmin DR/TV2',
			'percent_combo' => 0,
			],
		'dan_facebook_minority_dr_20180710' => [
			'name' => 'FBmin DR/TV2 v.2',
			'percent_combo' => 0,
			],
		'dan_facebook_minority_negative' => [
			'name' => 'FBmin-neg',
			'percent_combo' => 0,
			],
		'dan_facebook_minority_negative_dr' => [
			'name' => 'FBmin-neg DR/TV2',
			'percent_combo' => 0,
			],
		'dan_firma_1' => [
			'name' => 'Firma_1',
			],
		'dan_firma_2' => [
			'name' => 'Firma_2',
			],
		'dan_firma_3' => [
			'name' => 'Firma_3',
			],
		'dan_information_all' => [
			'name' => 'Information 1996-2008',
			'percent_combo' => 15,
			],
		'dan_leipzig' => [
			'name' => 'Leipzig internet corpus',
			],
		'dan_loke' => [
			'name' => 'dfk-loke',
			],
		'dan_munk' => [
			'name' => 'Munk-korpus',
			],
		'dan_skalk' => [
			'name' => 'dfk-skalk',
			],
		'dan_smik' => [
			'name' => 'Smik',
			],
		'dan_studiestart' => [
			'name' => 'SpUni',
			],
		'dan_vimu2' => [
			'name' => 'VIMU',
			],
		'dan_wiki_2018' => [
			'name' => 'Wikipedia',
			'percent_combo' => 20,
			],
		'dan_youtube' => [
			'name' => 'Youtube',
			],
		'dan_twitter' => [
			'name' => 'Twitter (2008-2022 Aug)',
			'percent_combo' => 25,
			'subs' => array_combine(range(2017, 2022), range(2017, 2022)),
			],
		/*
		'dan_c2000a' => [
			'name' => '',
			],
		'dan_information_a' => [
			'name' => 'Information 1996-2000',
			],
		'dan_information_b' => [
			'name' => 'Information 2000-2004',
			],
		'dan_information_c' => [
			'name' => 'Information 2004-2008',
			],
		'dan_kdk2010' => [
			'name' => 'Korpus2010',
			],
		'dan_wiki' => [
			'name' => 'Wikipedia',
			],
		//*/
		],
	'deu' => [
		'deu_europarl' => [
			'name' => 'Europarl',
			'percent_combo' => 5,
			],
		'deu_leipzig' => [
			'name' => 'Leipzig Internetcorpus',
			'percent_combo' => 25,
			],
		'deu_bzk' => [
			'name' => 'BZK',
			'percent_combo' => 2.5,
			],
		'deu_mak' => [
			'name' => 'MAK',
			'percent_combo' => 2.5,
			],
		'deu_ecide3' => [
			'name' => 'ECIDE3',
			'percent_combo' => 15,
			],
		'deu_facebook_minority_20180710' => [
			'name' => 'Facebook (minority)',
			],
		'deu_wiki_2019a' => [
			'name' => 'Wikipedia (2019a)',
			'percent_combo' => 12.5,
			],
		'deu_wiki_2019b' => [
			'name' => 'Wikipedia (2019b)',
			'percent_combo' => 12.5,
			],
		'deu_twitter_2008_2017' => [
			'name' => 'Twitter (2008-2017)',
			'percent_combo' => 4.16,
			'subs' => [
				'2017' => '2017',
				],
			],
		'deu_twitter_2018' => [
			'name' => 'Twitter (2018)',
			'percent_combo' => 4.16,
			],
		'deu_twitter_2019' => [
			'name' => 'Twitter (2019)',
			'percent_combo' => 4.16,
			],
		'deu_twitter_2020' => [
			'name' => 'Twitter (2020)',
			'percent_combo' => 4.16,
			],
		'deu_twitter_2021' => [
			'name' => 'Twitter (2021)',
			'percent_combo' => 4.16,
			],
		'deu_twitter_2022' => [
			'name' => 'Twitter (2022 Jan-Aug)',
			'percent_combo' => 4.16,
			],
		],
	'eng' => [
		'eng_europarl' => [
			'name' => 'Europarl',
			'percent_combo' => 10,
			],
		'eng_wiki_a' => [
			'name' => 'Wikipedia (a)',
			'percent_combo' => 6.66,
			],
		'eng_wiki_b' => [
			'name' => 'Wikipedia (b)',
			'percent_combo' => 6.66,
			],
		'eng_wiki_c' => [
			'name' => 'Wikipedia (c)',
			'percent_combo' => 6.66,
			],
		'eng_bnc_written' => [
			'name' => 'BNC-written',
			'percent_combo' => 15,
			],
		'eng_bnc_spoken' => [
			'name' => 'BNC-spoken',
			'percent_combo' => 15,
			],
		'eng_chat' => [
			'name' => 'Chat corpus',
			],
		'eng_shakespeare' => [
			'name' => 'KEMPE',
			],
		'eng_supreme' => [
			'name' => 'Supreme Court Dialogues',
			],
		'eng_ucla_2005_2009' => [
			'name' => 'UCLA CSA television news 2005-2009',
			'percent_combo' => 10,
			],
		'eng_ucla_2010_2012' => [
			'name' => 'UCLA CSA television news 2010-2012',
			'percent_combo' => 10,
			],
		'eng_wiki_conversations' => [
			'name' => 'Wikipedia Talkpages',
			],
		'eng_enron_a' => [
			'name' => 'Enron e-mails (a)',
			'percent_combo' => 6.66,
			],
		'eng_enron_b' => [
			'name' => 'Enron e-mails (b)',
			'percent_combo' => 6.66,
			],
		'eng_enron_c' => [
			'name' => 'Enron e-mails (c)',
			'percent_combo' => 6.66,
			],
		'eng_email' => [
			'name' => 'E-mail corpus',
			],
		'eng_teresa' => [
			'name' => 'E-mail openings',
			],
		'eng_beauty_blather' => [
			'name' => 'Beauty blog',
			],
		],
	'epo' => [
		'epo_elibrejo' => [
			'name' => 'Esperanto literature',
			'percent_combo' => 30,
			],
		'epo_eventoj' => [
			'name' => 'Eventoj news letter',
			'percent_combo' => 10,
			],
		'epo_frazoj' => [
			'name' => 'TTT 2004',
			'percent_combo' => 0,
			],
		'epo_monato' => [
			'name' => 'Monato magazine',
			'percent_combo' => 10,
			],
		'epo_uniq_ttt' => [
			'name' => 'TTT 2009',
			'percent_combo' => 25,
			],
		'epo_wiki' => [
			'name' => 'Wikipedia 2005',
			'percent_combo' => 0,
			],
		'epo_wiki_2010' => [
			'name' => 'Wikipedia 2010',
			'percent_combo' => 25,
			],
		'epo_zamenhof' => [
			'name' => 'Zamenhof classics',
			],
		],
	'spa' => [
		'spa_camtie' => [
			'name' => 'CAMTIE',
			'percent_combo' => 5,
			],
		'spa_ecies2' => [
			'name' => 'ECIES2',
			'percent_combo' => 5,
			],
		'spa_europarl' => [
			'name' => 'Europarl',
			'percent_combo' => 20,
			],
		'spa_web1' => [
			'name' => 'Internet safe',
			'percent_combo' => 45,
			],
		'spa_web2' => [
			'name' => 'Internet unsafe',
			'percent_combo' => 0,
			],
		'spa_web_role' => [
			'name' => 'Internet with semantic',
			'percent_combo' => 0,
			],
		'spa_wiki' => [
			'name' => 'Wikipedia',
			'percent_combo' => 25,
			],
		],
	'fao' => [
		'fao_sosialurin' => [
			'name' => 'Sosialurin',
			'percent_combo' => 50,
			],
		'fao_wiki' => [
			'name' => 'Wikipedia',
			'percent_combo' => 50,
			],
		],
	'fra' => [
		'fra_ecifr1' => [
			'name' => 'ECIFR1',
			'percent_combo' => 30,
			],
		'fra_europarl' => [
			'name' => 'Europarl',
			'percent_combo' => 30,
			],
		'fra_wiki' => [
			'name' => 'Wikipedia',
			'percent_combo' => 40,
			],
		],
	'ice' => [
		'ice_wiki' => [
			'name' => 'Wikipedia',
			'percent_combo' => 100,
			],
		],
	'ita' => [
		'ita_europarl' => [
			'name' => 'Europarl',
			'percent_combo' => 50,
			],
		'ita_wiki' => [
			'name' => 'Wikipedia',
			'percent_combo' => 50,
			],
		],
	'nor' => [
		'nor_leipzig' => [
			'name' => 'Leipzig Internet Corpus',
			'percent_combo' => 50,
			],
		'nor_wiki' => [
			'name' => 'Wikipedia',
			'percent_combo' => 50,
			],
		],
	'por' => [
		'por_children' => [
			'name' => 'Children',
			],
		'por_colonia' => [
			'name' => 'COLONIA',
			],
		'por_coral' => [
			'name' => 'C-ORAL',
			],
		'por_corpus_pub' => [
			'name' => 'Ad corpus',
			],
		'por_europarl' => [
			'name' => 'Europarl',
			'percent_combo' => 10,
			],
		'por_floresta_cf' => [
			'name' => 'Floresta-Folha',
			],
		'por_floresta_cp' => [
			'name' => 'Floresta-Público',
			],
		'por_folha' => [
			'name' => 'Folha de São Paulo',
			'percent_combo' => 35,
			],
		'por_folha_sem' => [
			'name' => 'Folha with semantics',
			'percent_combo' => 0,
			],
		'por_netlang' => [
			'name' => 'Netlang (Hate speech)',
			'percent_combo' => 10,
			],
		'por_nurc' => [
			'name' => 'NURC',
			],
		'por_publico' => [
			'name' => 'Público',
			'percent_combo' => 35,
			],
		'por_publico_91' => [
			'name' => 'Público-91',
			'percent_combo' => 0,
			],
		'por_publico_92' => [
			'name' => 'Público-92',
			'percent_combo' => 0,
			],
		'por_publico_93' => [
			'name' => 'Público-93',
			'percent_combo' => 0,
			],
		'por_publico_94' => [
			'name' => 'Público-94',
			'percent_combo' => 0,
			],
		'por_publico_95' => [
			'name' => 'Público-95',
			'percent_combo' => 0,
			],
		'por_publico_96' => [
			'name' => 'Público-96',
			'percent_combo' => 0,
			],
		'por_publico_97' => [
			'name' => 'Público-97',
			'percent_combo' => 0,
			],
		'por_publico_98' => [
			'name' => 'Público-98',
			'percent_combo' => 0,
			],
		'por_wiki' => [
			'name' => 'Wikipedia',
			'percent_combo' => 10,
			],
		],
	'ron' => [
		'ron_business' => [
			'name' => 'Business',
			'percent_combo' => 100,
			],
		],
	'swe' => [
		'swe_gp_all' => [
			'name' => 'Göteborgsposten 1993-2003',
			'percent_combo' => 55,
			],
		'swe_leipzig' => [
			'name' => 'Leipzig Internet corpus',
			'percent_combo' => 10,
			],
		'swe_wiki' => [
			'name' => 'Wikipedia',
			'percent_combo' => 25,
			],
		'swe_europarl' => [
			'name' => 'Europarl',
			'percent_combo' => 10,
			],
		],
	];
