#ifndef SHARED_HPP_a670dcc6836ad6396e7df2c96ed85ecf57dc71bb
#define SHARED_HPP_a670dcc6836ad6396e7df2c96ed85ecf57dc71bb

#include <string>
#include <string_view>
#include <unicode/ustring.h>
#include <unicode/utext.h>
using namespace icu;

template<typename T>
constexpr inline int32_t SI32(T t) {
	return static_cast<int32_t>(t);
}

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
	auto cap = SI32(str.size()*4);
	auto buf = target.getBuffer(str.size()*4);
	int32_t destlen = 0, subs = 0;
	u_strFromUTF8WithSub(buf, cap, &destlen, str.data(), str.size(), U'\uFFFD', &subs, &status);
	if (U_FAILURE(status)) {
		throw std::runtime_error(concat("ERROR in UnicodeString_fromUTF8:\n", u_errorName(status), "\nInput: ", str));
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

#endif
