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

## TODO
* Histogram:
  * Click on bar to jump to that place in the table
  * Click on table line to show all hits from that period
  * Menu to pick which field to graph
* Maybe split off more dynamic fields
