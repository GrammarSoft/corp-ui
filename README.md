# Indexing corpora

Once a corpus has been Manatee'd, add it to the interface and index it with these steps:

* If there are interesting subcorpora, run e.g. `mksubc ~/registry/dan_twitter ~/corpora/dan_twitter/subc/ ~/registry/dan_twitter.subc`
```
mkdir -pv ~/corpora/dan_twitter/dbs  ~/corpora/dan_twitter/tmp
cd ~/corpora/dan_twitter/tmp

# Count tokens, absolute frequencies, and histograms. Use -total if there are no lstamp with years
~/public_html/_bin/decodevert-word-lex ~/registry/dan_twitter | nice -n20 time ~/public_html/_src/build/index-corpus-year-lstamp
cat commands.sql | time sqlite3 stats.sqlite

# Calculate relative frequencies
nice -n20 time ~/public_html/_bin/stats-calc ~/corpora/dan_twitter/tmp/stats.sqlite

mv -v ~/corpora/dan_twitter/tmp/stats.sqlite ~/corpora/dan_twitter/dbs/stats.sqlite
rm -rf ~/corpora/dan_twitter/tmp
```
* TODO: Update global stats for the language
* Edit `_inc/config.php` to add it and all subcorpora to the `$GLOBALS['-corpora']` array.
