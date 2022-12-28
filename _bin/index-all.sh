cd ~/storage/registry
ls -1 --color=no | grep -vF . | xargs -P3 -r -IX bash -c 'if [[ -s ~/storage/corpora/X/meta ]]; then exit; fi; echo "Start X"; rm -rfv ~/storage/corpora/X/tmp; mkdir -pv ~/storage/corpora/X/tmp; cd ~/storage/corpora/X/tmp; nice -n20 time -o decode.time ~/public_html/_bin/decodevert-word-lex ~/storage/registry/X 2>decode.err | /usr/bin/time -o index.time ~/public_html/_src/build/index-corpus-total 2>index.err; echo "Stop X"'

ls -1 --color=no | grep -vF . | xargs -P3 -r -IX bash -c 'if [[ -s ~/storage/corpora/X/meta ]]; then exit; fi; echo "Start X"; cd ~/storage/corpora/X/tmp; cat commands.sql | nice -n20 time -o sqlite.time sqlite3 stats.sqlite; echo "Stop X"'

ls -1 --color=no | grep -vF . | xargs -P3 -r -IX bash -c 'if [[ -s ~/storage/corpora/X/meta ]]; then exit; fi; echo "Start X"; cd ~/storage/corpora/X/tmp; mkdir -pv ~/storage/corpora/X/meta; mv -v stats.sqlite ../meta; echo "Stop X"'
