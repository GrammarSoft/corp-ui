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
using namespace icu;
namespace bc = ::boost::container;

template<typename T>
constexpr inline int64_t SI64(T t) {
	return static_cast<int64_t>(t);
}

inline void utext_openUTF8(UText& ut, std::string_view xc) {
	UErrorCode status = U_ZERO_ERROR;
	utext_openUTF8(&ut, xc.data(), SI64(xc.size()), &status);
	if (U_FAILURE(status)) {
		throw std::runtime_error(u_errorName(status));
	}
}

template<typename Char>
inline bool is_space(Char c) {
	return (c == ' ' || c == '\t' || c == '\r' || c == '\n');
}

inline void remove_prefix(std::string& str, size_t n) {
	str.erase(0, n);
}
inline void remove_prefix(std::string_view& str, size_t n) {
	str.remove_prefix(n);
}
inline void remove_suffix(std::string& str, size_t n = 1) {
	str.erase(str.size() - n);
}
inline void remove_suffix(std::string_view& str, size_t n = 1) {
	str.remove_suffix(n);
}

template<typename Str>
inline void trim(Str& str) {
	while (!str.empty() && is_space(str.back())) {
		remove_suffix(str);
	}
	size_t h = 0;
	for (; h < str.size() && is_space(str[h]); ++h) {
	}
	remove_prefix(str, h);
}

namespace details {
	inline void _concat(std::string&) {
	}

	// ToDo: C++17 renders this function obsolete
	template<typename... Args>
	inline void _concat(std::string& msg, std::string_view t, Args... args) {
		msg += t;
		_concat(msg, args...);
	}

	template<typename T, typename... Args>
	inline void _concat(std::string& msg, const T& t, Args... args) {
		msg.append(t);
		_concat(msg, args...);
	}
}

template<typename T, typename... Args>
inline std::string concat(const T& value, Args... args) {
	std::string msg(value);
	details::_concat(msg, args...);
	return msg;
}

template<typename Str>
inline void UnicodeString_fromUTF8(UnicodeString& target, Str str) {
	UErrorCode status = U_ZERO_ERROR;
	auto cap = target.getCapacity();
	auto buf = target.getBuffer(str.size()*4);
	int32_t destlen = 0, subs = 0;
	u_strFromUTF8WithSub(buf, cap, &destlen, str.data(), str.size(), U'\uFFFD', &subs, &status);
	if (U_FAILURE(status)) {
		throw std::runtime_error(u_errorName(status));
	}
	target.releaseBuffer(destlen);
}

// Enable C++20 transparent lookup, cf. https://ibob.bg/blog/2022/09/17/transparent-lookups-for-maps-and-sets/
struct string_hash {
	using hash_type = std::hash<std::string_view>;
	using is_transparent = void;
	size_t operator()(const char* str) const { return hash_type{}(str); }
	size_t operator()(std::string_view str) const { return hash_type{}(str); }
	size_t operator()(std::string const& str) const { return hash_type{}(str); }
};

int main() {
	using namespace std::literals::string_literals;

	enum {
		UTC = 0,
		Local,
	};
	std::array<std::string_view,2> Names{"utc", "local"};
	auto todo = {UTC, Local};

	std::unordered_set<std::string, string_hash, std::equal_to<>> strings;
	auto memoize = [&](auto rv){
		auto it = strings.find(rv);
		if (it != strings.end()) {
			return std::string_view(*it);
		}
		auto ins = strings.insert(std::move(std::string(rv)));
		return std::string_view(*ins.first);
	};

	boost::unordered_map<std::string_view, std::string_view> cache_lc;
	boost::unordered_map<std::string_view, std::string_view> cache_nd;

	UErrorCode status = U_ZERO_ERROR;
	u_init(&status);
	if (U_FAILURE(status) && status != U_FILE_ACCESS_ERROR) {
		throw std::runtime_error(u_errorName(status));
	}

	auto any2nfc = Transliterator::createInstance(UnicodeString::fromUTF8("any-nfc"), UTRANS_FORWARD, status);
	auto any2name = Transliterator::createInstance(UnicodeString::fromUTF8("any-name"), UTRANS_FORWARD, status);
	auto name2any = Transliterator::createInstance(UnicodeString::fromUTF8("name-any"), UTRANS_FORWARD, status);
	auto any2lower = Transliterator::createInstance(UnicodeString::fromUTF8("any-lower"), UTRANS_FORWARD, status);
	auto any2latin = Transliterator::createInstance(UnicodeString::fromUTF8("any-latin"), UTRANS_FORWARD, status);
	auto latin2ascii = Transliterator::createInstance(UnicodeString::fromUTF8("latin-ascii"), UTRANS_FORWARD, status);
	auto remove = Transliterator::createInstance(UnicodeString::fromUTF8("[:Modifier_Symbol:] remove; [\\u0100-\\u7fff] remove;"), UTRANS_FORWARD, status);

	auto dotless = UnicodeString::fromUTF8(" DOTLESS ");
	auto space = UnicodeString::fromUTF8(" ");
	auto nothing = UnicodeString::fromUTF8("");
	RegexMatcher rx_with(UnicodeString::fromUTF8(R"X( WITH [^}]+)X"), UREGEX_CASE_INSENSITIVE, status);

	std::string _c_tmp_str;
	UnicodeString _c_tmp_us;
	UnicodeString _c_line;
	auto conv_lc = [&](std::string_view org) {
		if (cache_lc.count(org)) {
			return cache_lc[org];
		}

		UnicodeString_fromUTF8(_c_line, org);
		any2nfc->transliterate(_c_line);
		any2lower->transliterate(_c_line);

		_c_tmp_str.clear();
		_c_line.toUTF8String(_c_tmp_str);
		auto rv = memoize(_c_tmp_str);
		cache_nd[org] = rv;
		cache_nd[rv] = rv;
		return rv;
	};

	auto conv_nd = [&](std::string_view org) {
		if (cache_nd.count(org)) {
			return cache_nd[org];
		}

		UnicodeString_fromUTF8(_c_line, org);
		//any2nfc->transliterate(_c_line);

		any2name->transliterate(_c_line);
		_c_line.findAndReplace(dotless, space);
		rx_with.reset(_c_line);
		_c_tmp_us = rx_with.replaceAll(nothing, status);
		std::swap(_c_tmp_us, _c_line);
		name2any->transliterate(_c_line);

		//any2lower->transliterate(_c_line);
		any2latin->transliterate(_c_line);
		latin2ascii->transliterate(_c_line);
		remove->transliterate(_c_line);

		_c_tmp_str.clear();
		_c_line.toUTF8String(_c_tmp_str);
		auto rv = memoize(_c_tmp_str);
		cache_nd[org] = rv;
		cache_nd[rv] = rv;
		return rv;
	};

	using hist_t = bc::flat_map<size_t, boost::unordered_map<size_t, bc::flat_map<char, size_t>>>;
	std::array<hist_t,2> hists;

	UText tmp_ut = UTEXT_INITIALIZER;
	RegexMatcher rx_stamp(UnicodeString::fromUTF8(R"X( stamp="(\d+)-(\d+)-(\d+) (\d+))X"), UREGEX_CASE_INSENSITIVE, status);
	RegexMatcher rx_lstamp(UnicodeString::fromUTF8(R"X( lstamp="(\d+)-(\d+)-(\d+) (\d+))X"), UREGEX_CASE_INSENSITIVE, status);
	RegexMatcher rx_article(UnicodeString::fromUTF8(R"X( (?:tweet|article|title)="([^"]+))X"), UREGEX_CASE_INSENSITIVE, status);
	RegexMatcher rx_curc(UnicodeString::fromUTF8(R"X(^¤+\t)X"), 0, status);

	RegexMatcher rx_word(UnicodeString::fromUTF8(R"X(^[\p{L}][- '`´\p{L}\p{M}]*$)X"), 0, status);
	RegexMatcher rx_number(UnicodeString::fromUTF8(R"X(^[\p{N}\d][- \p{N}\d]*$)X"), 0, status);
	RegexMatcher rx_alnum(UnicodeString::fromUTF8(R"X(^[\p{L}\p{N}\d][- \p{L}\p{M}\p{N}\d]*$)X"), 0, status);
	RegexMatcher rx_punct(UnicodeString::fromUTF8(R"X(^[\p{P},.:;!]+$)X"), 0, status);
	RegexMatcher rx_emoji(UnicodeString::fromUTF8(R"X(^emo-)X"), UREGEX_CASE_INSENSITIVE, status);

	if (U_FAILURE(status)) {
		throw std::runtime_error(u_errorName(status));
	}

	enum {
		Y = 0,
		Y_m,
		Y_m_d,
		Y_m_d_H,
		H,
		N_Lasts,
	};
	using Last = std::array<size_t,N_Lasts>;
	std::array<Last,2> lasts{};
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
	Count totals{};
	using count_t = bc::flat_map<size_t, Count>;
	std::array<count_t,2> totals_t{};

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
	std::array<Freq,N_Fields> freqs{};
	using freq_t = bc::flat_map<size_t, std::array<Freq,N_Fields>>;
	std::array<freq_t,2> freqs_t{};

	std::string line;
	std::string tmp;
	size_t cnt = 0;
	for (; std::getline(std::cin, line); ++cnt) {
		if (cnt % 50000 == 0) {
			fprintf(stderr, "Line %zu\r", cnt);
		}
		utext_openUTF8(tmp_ut, line);

		if (line[0] == '<' && line[1] == 's' && line[2] == ' ') {
			auto stamps = {std::pair(&rx_stamp, UTC), std::pair(&rx_lstamp, Local)};
			for (auto& s : stamps) {
				auto& rx = *s.first;
				auto& last = lasts[s.second];

				rx.reset(&tmp_ut);
				if (rx.find()) {
					auto b = rx.start(1, status);
					auto e = rx.end(1, status);
					tmp.assign(line, b, e-b);
					last[Y] = std::stoull(tmp);

					b = rx_stamp.start(2, status);
					e = rx_stamp.end(2, status);
					tmp.assign(line, b, e-b);
					last[Y_m] = last[Y]*100 + std::stoull(tmp);

					b = rx_stamp.start(3, status);
					e = rx_stamp.end(3, status);
					tmp.assign(line, b, e-b);
					last[Y_m_d] = last[Y_m]*100 + std::stoull(tmp);

					b = rx_stamp.start(4, status);
					e = rx_stamp.end(4, status);
					tmp.assign(line, b, e-b);
					last[H] = std::stoull(tmp);
					last[Y_m_d_H] = last[Y_m_d]*100 + last[H];
				}
			}

			rx_article.reset(&tmp_ut);
			if (rx_article.find()) {
				auto b = rx_article.start(1, status);
				auto e = rx_article.end(1, status);
				tmp.assign(line, b, e-b);
				auto art = memoize(tmp);
				if (last_article != art) {
					for (auto& t : todo) {
						auto& hist = hists[t];
						auto& last = lasts[t];
						for (auto w : last) {
							++hist[last[Y]][w]['a'];
						}
					}
					last_article = art;
				}
			}

			for (auto& t : todo) {
				auto& hist = hists[t];
				auto& last = lasts[t];
				for (auto w : last) {
					++hist[last[Y]][w]['s'];
				}
			}
			continue;
		}

		rx_curc.reset(&tmp_ut);
		if ((line[0] == '<' && line[1] == '/' && line[2] == 's' && line[3] == '>') || rx_curc.find()) {
			continue;
		}

		for (auto& t : todo) {
			auto& hist = hists[t];
			auto& last = lasts[t];
			for (auto w : last) {
				++hist[last[Y]][w]['t'];
			}
		}

		auto tss = {std::pair(UTC, lasts[UTC][Y]), std::pair(Local, lasts[Local][Y])};

		++totals[Total];
		for (auto& ts : tss) {
			++totals_t[ts.first][ts.second][Total];
		}

		std::array<std::string_view,2> m{line, line};
		m[0] = m[0].substr(0, m[0].find('\t'));
		m[1] = m[1].substr(m[0].size()+1);
		m[1] = m[1].substr(0, m[1].find('\t'));
		trim(m[0]);
		trim(m[1]);

		if (m[0].size() == 0) {
			continue;
		}

		utext_openUTF8(tmp_ut, m[1]);
		rx_emoji.reset(&tmp_ut);
		if (rx_emoji.find()) {
			++totals[Emojis];
			for (auto& ts : tss) {
				++totals_t[ts.first][ts.second][Emojis];
			}
		}

		utext_openUTF8(tmp_ut, m[0]);
		rx_word.reset(&tmp_ut);
		rx_number.reset(&tmp_ut);
		rx_alnum.reset(&tmp_ut);
		rx_punct.reset(&tmp_ut);

		bool tally = false;
		if (rx_word.find()) {
			++totals[Words];
			for (auto& ts : tss) {
				++totals_t[ts.first][ts.second][Words];
			}
			tally = true;
		}
		else if (rx_number.find()) {
			++totals[Numbers];
			for (auto& ts : tss) {
				++totals_t[ts.first][ts.second][Numbers];
			}
			tally = true;
		}
		else if (rx_alnum.find()) {
			++totals[Alnums];
			for (auto& ts : tss) {
				++totals_t[ts.first][ts.second][Alnums];
			}
			tally = true;
		}
		else if (rx_punct.find()) {
			++totals[Puncts];
			for (auto& ts : tss) {
				++totals_t[ts.first][ts.second][Puncts];
			}
		}
		else {
			++totals[Others];
			for (auto& ts : tss) {
				++totals_t[ts.first][ts.second][Others];
			}
		}

		if (tally) {
			for (auto& t : todo) {
				auto& hist = hists[t];
				auto& last = lasts[t];
				for (auto w : last) {
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

			++freqs[word][m[0]];
			++freqs[lex][m[1]];
			++freqs[word_lc][lc[0]];
			++freqs[lex_lc][lc[1]];
			++freqs[word_nd][nd[0]];
			++freqs[lex_nd][nd[1]];

			for (auto& t : todo) {
				auto& last = lasts[t];
				auto& freqs = freqs_t[t][last[Y]];

				++freqs[word][m[0]];
				++freqs[lex][m[1]];
				++freqs[word_lc][lc[0]];
				++freqs[lex_lc][lc[1]];
				++freqs[word_nd][nd[0]];
				++freqs[lex_nd][nd[1]];
			}
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
	for (auto& t : totals) {
		fprintf(out, "\t%zu", t);
	}
	fprintf(out, "\n");

	for (auto& h : todo) {
		for (auto& ts : totals_t[h]) {
			fprintf(out, "%zu_%s", ts.first, Names[h].data());
			for (auto& t : ts.second) {
				fprintf(out, "\t%zu", t);
			}
			fprintf(out, "\n");
		}
	}
	fclose(out);
	sql.append("BEGIN;\n");
	sql.append(".import counts.tsv counts\n");
	sql.append("COMMIT;\n\n");

	// Output histogram counts
	for (auto& h : todo) {
		auto keys = {'a', 's', 't', 'w'};
		for (auto& ys : hists[h]) {
			auto tname = concat("hist_", std::to_string(ys.first), "_", Names[h]);
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
	sql.append(concat("CREATE TABLE freq_", fNames[i], R"( (
	f_text TEXT NOT NULL,
	f_abs INTEGER NOT NULL,
	f_rel REAL NOT NULL,
	PRIMARY KEY (f_text)
) WITHOUT ROWID;

)"));
		auto out = fopen(concat("freq_", fNames[i],".tsv").c_str(), "wb");
		for (auto& kv : freqs[i]) {
			if (kv.second < 2) {
				continue;
			}
			fprintf(out, "%s\t%zu\t0\n", kv.first.data(), kv.second);
		}
		fclose(out);
		sql.append("BEGIN;\n");
		sql.append(concat(".import freq_", fNames[i],".tsv freq_", fNames[i], "\n"));
		sql.append("COMMIT;\n\n");
	}

	for (auto& h : todo) {
		for (auto& fts : freqs_t[h]) {
			for (size_t i=0 ; i<N_Fields ; ++i) {
				auto tname = concat("freq_", std::to_string(fts.first), "_", Names[h], "_", fNames[i]);
				sql.append(concat("CREATE TABLE ", tname, R"( (
	f_text TEXT NOT NULL,
	f_abs INTEGER NOT NULL,
	f_rel REAL NOT NULL,
	PRIMARY KEY (f_text)
) WITHOUT ROWID;

)"));

				auto fname = concat(tname,".tsv");
				auto out = fopen(fname.c_str(), "wb");
				for (auto& kv : fts.second[i]) {
					if (kv.second < 2) {
						continue;
					}
					fprintf(out, "%s\t%zu\t0\n", kv.first.data(), kv.second);
				}
				fclose(out);
				sql.append("BEGIN;\n");
				sql.append(concat(".import ", fname, " ", tname, "\n"));
				sql.append("COMMIT;\n\n");
			}
		}
	}

	out = fopen("commands.sql", "wb");
	fprintf(out, "%s", sql.c_str());
	fclose(out);

	utext_close(&tmp_ut);
}
