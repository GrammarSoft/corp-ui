# Indexing corpora

Once a corpus has been Manatee'd, add it to the interface and index it with these steps:

* If there are interesting subcorpora, run e.g. `mksubc ~/storage/registry/dan_twitter ~/storage/corpora/dan_twitter/subc/ ~/storage/registry/dan_twitter.subc`
```
mkdir -pv ~/storage/corpora/dan_twitter/meta  ~/storage/corpora/dan_twitter/tmp
cd ~/storage/corpora/dan_twitter/tmp

# Count tokens, absolute frequencies, and histograms. Use -total if there are no lstamp with years
~/public_html/_bin/decodevert-word-lex-pos ~/storage/registry/dan_twitter | time ~/public_html/_src/build/index-corpus-year-lstamp
cat commands.sql | time sqlite3 stats.sqlite

# Calculate relative frequencies
time ~/public_html/_bin/stats-calc ~/storage/corpora/dan_twitter/tmp/stats.sqlite

mv -v ~/storage/corpora/dan_twitter/tmp/stats.sqlite ~/storage/corpora/dan_twitter/meta/stats.sqlite
rm -rf ~/storage/corpora/dan_twitter/tmp
```
* Edit `_inc/config.php` to add it and all subcorpora to the `$GLOBALS['-corpora']` array.
* Update global stats for the language with `time ~/public_html/_bin/stats-combine dan`

* If there are group-by attributes, index those, passing a colon-separated list of attributes:
```
cd ~/storage/corpora/dan_literature/meta
~/public_html/_bin/decodevert-word-lex ~/storage/registry/dan_literature | grep -v '===NONE===' | time ~/public_html/_bin/group-by group-by.sqlite 'author:title:year'
```

## TODO
* Share corpus search without password
* If no corpora are selected, pick the largest unprotected ones
* User-option to show "media corpus"-like contexts with multiple sentences or paragraphs
* Highlight parents and siblings if searched for
* Per-language help links in top to CG grammar docs
* Break down Group By hits into per-s histogram
* Annotate Group-By bars with unique column values not part of the group-by
* Group-By type-token relation via lex_POS
  * Fix sparse calculation
* View the whole work (for open corpora)
* 2D queries as scatter plots (E.g., Q+/- and a semantic class)
  * Fields to limit on absolute X/Y value
  * User-defined cutoff, default 0.1 or 0.05
  * Toggle text
* Sparse show only in table
* Use semantic vector model to disambiguate semantics
* Double-check c_words + c_numbers + c_alnums in stats-combine
* Check multi-corpus bracketing
* N-grams should be clickable - this will need query results as tab-separated
* Multi-word expressions are hard to search for without CQP-speak - maybe have a per-corpus/language list of them
* When coming from group-by or histogram, freq and ngrams should be disabled, with a message
