#include "shared.hpp"
#include <iostream>
#include <string>
#include <string_view>
#include <map>
#include <array>
#include <cstdint>
#include <unordered_set>
#include <boost/container/flat_map.hpp>
#include <boost/unordered_set.hpp>
#include <boost/unordered_map.hpp>
#include <unicode/uclean.h>
#include <unicode/ustring.h>
#include <unicode/utext.h>
#include <unicode/translit.h>
#include <unicode/regex.h>

int main() {
	#include "preamble.cpp"

	enum {
		Y = 0,
		Y_m,
		Y_m_d,
		Y_m_d_H,
		H,
		N_Lasts,
	};
	using Last = std::array<size_t,N_Lasts>;
	Last last{};
	std::string_view last_article;

	enum {
		Total = 0,
		Words,
		Numbers,
		Alnums,
		Puncts,
		Emojis,
		Others,
		N_Counts,
	};
	//std::array<std::string_view,N_Counts> cNames{"total", "words", "numbers", "alnums", "puncts", "emojis", "others"};
	using Count = std::array<size_t,N_Counts>;
	Count total{};

	enum {
		word = 0,
		lex,
		word_lc,
		lex_lc,
		word_nd,
		lex_nd,
		N_Fields,
	};
	std::array<std::string_view,N_Fields> fNames{"word", "lex", "word_lc", "lex_lc", "word_nd", "lex_nd"};
	using Freq = boost::unordered_map<std::string_view,size_t>;
	std::array<Freq,N_Fields> freq{};

	using hist_t = bc::flat_map<size_t, boost::unordered_map<size_t, bc::flat_map<char, size_t>>>;
	hist_t hist;

	std::string line;
	std::string tmp;
	size_t cnt = 0;
	for (; std::getline(std::cin, line); ++cnt) {
		if (cnt % 50000 == 0) {
			fprintf(stderr, "Line %zu\r", cnt);
		}
		utext_openUTF8(tmp_ut, line);

		if (line[0] == '<' && line[1] == 's' && (line[2] == ' ' || line[2] == '>')) {
			rx_lstamp.reset(&tmp_ut);
			if (rx_lstamp.find()) {
				auto b = rx_lstamp.start(1, status);
				auto e = rx_lstamp.end(1, status);
				tmp.assign(line, b, e-b);
				last[Y] = std::stoull(tmp);

				b = rx_lstamp.start(2, status);
				e = rx_lstamp.end(2, status);
				tmp.assign(line, b, e-b);
				last[Y_m] = last[Y]*100 + std::stoull(tmp);

				b = rx_lstamp.start(3, status);
				e = rx_lstamp.end(3, status);
				tmp.assign(line, b, e-b);
				last[Y_m_d] = last[Y_m]*100 + std::stoull(tmp);

				b = rx_lstamp.start(4, status);
				e = rx_lstamp.end(4, status);
				tmp.assign(line, b, e-b);
				last[H] = std::stoull(tmp);
				last[Y_m_d_H] = last[Y_m_d]*100 + last[H];
			}

			rx_article.reset(&tmp_ut);
			if (rx_article.find()) {
				auto b = rx_article.start(1, status);
				auto e = rx_article.end(1, status);
				tmp.assign(line, b, e-b);
				auto art = memoize(tmp);
				if (last_article != art) {
					for (auto& w : last) {
						++hist[last[Y]][w]['a'];
					}
					last_article = art;
				}
			}

			for (auto& w : last) {
				++hist[last[Y]][w]['s'];
			}
			continue;
		}

		rx_curc.reset(&tmp_ut);
		if ((line[0] == '<' && line[1] == '/' && line[2] == 's' && line[3] == '>') || rx_curc.find()) {
			continue;
		}

		if (last[Y]) {
			for (auto& w : last) {
				++hist[last[Y]][w]['t'];
			}
		}

		++total[Total];

		std::array<std::string_view,2> m{line, line};
		m[0] = m[0].substr(0, m[0].find('\t'));
		m[1] = m[1].substr(m[0].size()+1);
		m[1] = m[1].substr(0, m[1].find('\t'));
		trim(m[0]);
		trim(m[1]);

		if (m[0].size() == 0 || m[1].size() == 0) {
			continue;
		}

		utext_openUTF8(tmp_ut, m[1]);
		rx_emoji.reset(&tmp_ut);
		if (rx_emoji.find()) {
			++total[Emojis];
		}

		utext_openUTF8(tmp_ut, m[0]);
		rx_word.reset(&tmp_ut);
		rx_number.reset(&tmp_ut);
		rx_alnum.reset(&tmp_ut);
		rx_punct.reset(&tmp_ut);

		bool tally = false;
		if (rx_word.find()) {
			++total[Words];
			tally = true;
		}
		else if (rx_number.find()) {
			++total[Numbers];
			tally = true;
		}
		else if (rx_alnum.find()) {
			++total[Alnums];
			tally = true;
		}
		else if (rx_punct.find()) {
			++total[Puncts];
		}
		else {
			++total[Others];
		}

		if (tally) {
			if (last[Y]) {
				for (auto& w : last) {
					++hist[last[Y]][w]['w'];
				}
			}

			if (m[0].size() > 75) {
				continue;
			}

			m[0] = memoize(m[0]);
			m[1] = memoize(m[1]);
			std::array<std::string_view,2> lc{conv_lc(m[0]), conv_lc(m[1])};
			std::array<std::string_view,2> nd{conv_nd(lc[0]), conv_nd(lc[1])};

			++freq[word][m[0]];
			++freq[lex][m[1]];
			++freq[word_lc][lc[0]];
			++freq[lex_lc][lc[1]];
			++freq[word_nd][nd[0]];
			++freq[lex_nd][nd[1]];
		}
	}
	fprintf(stderr, "Lines: %zu\n", cnt);

	std::string sql(R"(
PRAGMA journal_mode = delete;
PRAGMA page_size = 65536;
VACUUM;

PRAGMA auto_vacuum = INCREMENTAL;
PRAGMA case_sensitive_like = ON;
PRAGMA foreign_keys = OFF;
PRAGMA ignore_check_constraints = ON;
PRAGMA journal_mode = MEMORY;
PRAGMA locking_mode = EXCLUSIVE;
PRAGMA synchronous = OFF;
PRAGMA threads = 4;
PRAGMA trusted_schema = OFF;

.mode ascii
.separator "\t" "\n"

CREATE TABLE counts (
	c_which TEXT NOT NULL,
	c_total INTEGER NOT NULL,
	c_words INTEGER NOT NULL,
	c_numbers INTEGER NOT NULL,
	c_alnums INTEGER NOT NULL,
	c_puncts INTEGER NOT NULL,
	c_emojis INTEGER NOT NULL,
	c_other INTEGER NOT NULL,
	PRIMARY KEY (c_which)
) WITHOUT ROWID;

)");

	// Output counts of token categories
	auto out = fopen("counts.tsv", "wb");
	fprintf(out, "total");
	for (auto& t : total) {
		fprintf(out, "\t%zu", t);
	}
	fprintf(out, "\n");
	sql.append("BEGIN;\n");
	sql.append(".import counts.tsv counts\n");
	sql.append("COMMIT;\n\n");

	// Output histogram counts
	auto keys = {'a', 's', 't', 'w'};
	if (last[Y]) {
		for (auto& ys : hist) {
			auto tname = concat("hist_", std::to_string(ys.first));
			sql.append(concat("CREATE TABLE ", tname, R"( (
	h_group INTEGER NOT NULL,
	h_articles INTEGER NOT NULL,
	h_sentences INTEGER NOT NULL,
	h_tokens INTEGER NOT NULL,
	h_words INTEGER NOT NULL,
	PRIMARY KEY (h_group)
) WITHOUT ROWID;

)"));
			auto fname = concat(tname, ".tsv");
			auto out = fopen(fname.c_str(), "wb");
			for (auto& sk : ys.second) {
				fprintf(out, "%zu", sk.first);
				for (auto& key : keys) {
					fprintf(out, "\t%zu", sk.second[key]);
				}
				fprintf(out, "\n");
			}
			fclose(out);
			sql.append("BEGIN;\n");
			sql.append(concat(".import ", fname, " ", tname, "\n"));
			sql.append("COMMIT;\n\n");
		}
	}

	// Output token frequencies
	for (size_t i=0 ; i<N_Fields ; ++i) {
	sql.append(concat("CREATE TABLE freq_total_", fNames[i], R"( (
	f_text TEXT NOT NULL,
	f_abs INTEGER NOT NULL,
	f_rel REAL NOT NULL,
	PRIMARY KEY (f_text)
) WITHOUT ROWID;

)"));
		auto out = fopen(concat("freq_total_", fNames[i],".tsv").c_str(), "wb");
		for (auto& kv : freq[i]) {
			if (kv.second < 2) {
				continue;
			}
			fprintf(out, "%s\t%zu\t0\n", kv.first.data(), kv.second);
		}
		fclose(out);
		sql.append("BEGIN;\n");
		sql.append(concat(".import freq_total_", fNames[i],".tsv freq_total_", fNames[i], "\n"));
		sql.append("COMMIT;\n\n");
	}

	sql.append(R"(
PRAGMA locking_mode = NORMAL;
PRAGMA ignore_check_constraints = OFF;
VACUUM;
)");

	out = fopen("commands.sql", "wb");
	fprintf(out, "%s", sql.c_str());
	fclose(out);

	utext_close(&tmp_ut);
}
