cmake_minimum_required(VERSION 3.0 FATAL_ERROR)
cmake_policy(VERSION ${CMAKE_MAJOR_VERSION}.${CMAKE_MINOR_VERSION})
project(corpus CXX)

set(MASTER_PROJECT OFF)
if (CMAKE_CURRENT_SOURCE_DIR STREQUAL CMAKE_SOURCE_DIR)
	set(MASTER_PROJECT ON)
endif ()

if(MASTER_PROJECT AND NOT CMAKE_BUILD_TYPE)
	set(CMAKE_BUILD_TYPE "Release")
endif()

set(CMAKE_POSITION_INDEPENDENT_CODE ON)
set(CMAKE_MACOSX_RPATH ON)

include(GNUInstallDirs)

set(_FLAGS_COMMON "-Wall -Wextra -Wno-missing-field-initializers -Wno-deprecated -Wno-unused-parameter -fPIC")

include(CheckCXXCompilerFlag)

foreach(flag "-Wno-unused-result" "-flto")
	string(REGEX REPLACE "[^A-Za-z0-9]" "" _flag ${flag})
	CHECK_CXX_COMPILER_FLAG(${flag} COMPILER_SUPPORTS_${_flag})
	if(COMPILER_SUPPORTS_${_flag})
		set(_FLAGS_COMMON "${_FLAGS_COMMON} ${flag}")
	endif()
endforeach()
if(COMPILER_SUPPORTS_flto)
	set(CMAKE_EXE_LINKER_FLAGS_RELEASE "${CMAKE_EXE_LINKER_FLAGS_RELEASE} -flto")
endif()

set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} ${_FLAGS_COMMON} -fvisibility-inlines-hidden")
set(CMAKE_CXX_FLAGS_DEBUG "${CMAKE_CXX_FLAGS_DEBUG} -O0 -g3")
set(CMAKE_CXX_FLAGS_RELEASE "${CMAKE_CXX_FLAGS_RELEASE} -O3")

# Require latest possible C++ standard
foreach(flag "-std=c++23" "-std=c++2b" "-std=c++20")
	string(REGEX REPLACE "[^a-z0-9]" "-" _flag ${flag})
	CHECK_CXX_COMPILER_FLAG(${flag} COMPILER_SUPPORTS_${_flag})
	if(COMPILER_SUPPORTS_${_flag})
		set(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} ${flag}")
		set(_ENABLED_CXX ${flag})
		break()
	endif()
endforeach()
if(NOT _ENABLED_CXX)
	message(FATAL_ERROR "Could not enable at least C++20 - upgrade your compiler")
endif()

add_definitions(-D_POSIX_C_SOURCE=200112 -DBOOST_NO_CXX98_FUNCTION_BASE=1 -DUNISTR_FROM_CHAR_EXPLICIT=explicit -DUNISTR_FROM_STRING_EXPLICIT=explicit)

# Boost
find_package(Boost 1.53.0 REQUIRED)
include_directories(${Boost_INCLUDE_DIRS})
link_directories(${Boost_LIBRARY_DIRS})

# ICU
find_package(ICU 50.0 COMPONENTS i18n io uc data REQUIRED)
include_directories(${ICU_INCLUDE_DIRS})

macro(link_libs target)
	target_link_libraries(${target} ${Boost_LIBRARIES})
	target_link_libraries(${target} ${ICU_LIBRARIES})
endmacro()

add_executable(index-corpus index-corpus.cpp)
link_libs(index-corpus)

install(TARGETS index-corpus RUNTIME DESTINATION ${CMAKE_INSTALL_BINDIR})
