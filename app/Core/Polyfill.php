<?php

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle !== '' && substr($haystack, -strlen($needle)) === $needle;
    }
}

if (!function_exists('mb_strlen')) {
    function mb_strlen($str, $encoding = null) {
        return strlen($str);
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null, $encoding = null) {
        if ($length === null) {
            return substr($str, $start);
        }
        return substr($str, $start, $length);
    }
}

if (!function_exists('array_is_list')) {
    function array_is_list($arr) {
        if (!is_array($arr) || empty($arr)) {
            return false;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
