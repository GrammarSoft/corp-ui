using namespace icu;
namespace bc = ::boost::container;
using namespace std::literals::string_literals;

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
	if (cache_lc.size() > 10000000) {
		cache_lc.clear();
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
	if (cache_nd.size() > 10000000) {
		cache_nd.clear();
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

UText tmp_ut = UTEXT_INITIALIZER;
RegexMatcher rx_stamp(UnicodeString::fromUTF8(R"X( stamp="(\d+)-(\d+)-(\d+) (\d+))X"), UREGEX_CASE_INSENSITIVE, status);
RegexMatcher rx_lstamp(UnicodeString::fromUTF8(R"X( lstamp="(\d+)-(\d+)-(\d+) (\d+))X"), UREGEX_CASE_INSENSITIVE, status);
RegexMatcher rx_article(UnicodeString::fromUTF8(R"X( (?:tweet|article|title|oid)="([^"]+))X"), UREGEX_CASE_INSENSITIVE, status);
RegexMatcher rx_curc(UnicodeString::fromUTF8(R"X(^¤+\t)X"), 0, status);

RegexMatcher rx_word(UnicodeString::fromUTF8(R"X(^[\p{L}][- '`´\p{L}\p{M}]*$)X"), 0, status);
RegexMatcher rx_number(UnicodeString::fromUTF8(R"X(^[\p{N}\d][- \p{N}\d]*$)X"), 0, status);
RegexMatcher rx_alnum(UnicodeString::fromUTF8(R"X(^[\p{L}\p{N}\d][- \p{L}\p{M}\p{N}\d]*$)X"), 0, status);
RegexMatcher rx_punct(UnicodeString::fromUTF8(R"X(^[\p{P},.:;!]+$)X"), 0, status);
RegexMatcher rx_emoji(UnicodeString::fromUTF8(R"X(^emo-)X"), UREGEX_CASE_INSENSITIVE, status);

if (U_FAILURE(status)) {
	throw std::runtime_error(u_errorName(status));
}
