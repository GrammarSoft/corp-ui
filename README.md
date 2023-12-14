# Indexing corpora

Once a corpus has been Manatee'd, add it to the interface and index it with these steps:

* If there are interesting subcorpora, run e.g. `mksubc ~/storage/registry/dan_twitter ~/storage/corpora/dan_twitter/subc/ ~/storage/registry/dan_twitter.subc`
```
mkdir -pv ~/storage/corpora/dan_twitter/meta  ~/storage/corpora/dan_twitter/tmp
cd ~/storage/corpora/dan_twitter/tmp

# Count tokens, absolute frequencies, and histograms. Use -total if there are no lstamp with years
~/public_html/_bin/decodevert-word-lex ~/storage/registry/dan_twitter | time ~/public_html/_src/build/index-corpus-year-lstamp
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
* (Frequencies should include POS) - No, restrict it in the search and compare manually instead
* Share corpus search without password
* If no corpora are selected, pick the largest unprotected ones
* Adjustable context size
* Implement sibling search
* Highlight parents if searched for
* Per-language help links in top to CG grammar docs
* Per-corpus refine fields for S-attributes (single-corpus only)
* Stacked bar charts for grouped frequencies
