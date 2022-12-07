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

	auto conv_lc_nd = [&](std::string_view org) {
		if (cache_nd.count(org)) {
			return cache_nd[org];
		}
		if (cache_nd.size() > 10000000) {
			cache_nd.clear();
		}

		UnicodeString_fromUTF8(_c_line, org);
		any2nfc->transliterate(_c_line);
		any2lower->transliterate(_c_line);

		any2name->transliterate(_c_line);
		_c_line.findAndReplace(dotless, space);
		rx_with.reset(_c_line);
		_c_tmp_us = rx_with.replaceAll(nothing, status);
		std::swap(_c_tmp_us, _c_line);
		name2any->transliterate(_c_line);

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

	std::string line;
	while (std::getline(std::cin, line)) {
		auto out = conv_lc_nd(line);
		std::cout << out << '\n';
	}
}
