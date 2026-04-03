<?php
declare(strict_types=1);

namespace GatePay\Core\Utils;

use function chr;
use function extension_loaded;
use function function_exists;
use function ord;
use function preg_replace;
use function preg_replace_callback;
use function str_replace;
use function strlen;
use function substr;

/**
 * Utility class for handling UTF-8 encoding and validation.
 * This class provides methods to check for UTF-8 validity, encode and decode strings,
 * and sanitize input by removing invalid UTF-8 sequences. It also includes caching
 * mechanisms to optimize performance when checking for mbstring
 */
class Utf8
{
    /**
     * @var bool|null
     * Cache the result of checking for mbstring availability to avoid redundant checks in subsequent calls.
     */
    private static ?bool $mbStringAvailable = null;

    /**
     * Check if mbstring is available
     *
     * @return bool
     */
    public static function isMbStringAvailable(): bool
    {
        return self::$mbStringAvailable ??= extension_loaded('mbstring') || (
                function_exists('mb_check_encoding')
                && function_exists('mb_convert_encoding')
                && function_exists('mb_strlen')
            );
    }

    /**
     * Remove null bytes and control characters from a string. Optionally remove \0 sequences as well.
     * This is useful for sanitizing input that may contain malicious or unwanted characters.
     *
     * @param string $string
     * @param bool $slash_zero
     * @return string
     */
    public static function replaceNullString(string $string, bool $slash_zero = true): string
    {
        // Remove control characters (0x00-0x1F) and optionally remove \0 sequences
        $control_characters = [
            "\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08",
            "\x0B", "\x0C", "\x0E", "\x0F", "\x10", "\x11", "\x12", "\x13", "\x14",
            "\x15", "\x16", "\x17", "\x18", "\x19", "\x1A", "\x1B", "\x1C", "\x1D",
            "\x1E", "\x1F"
        ];
        $string = str_replace($control_characters, '', $string);
        if ($slash_zero) {
            $string = preg_replace('/\\\\+0+/', '', $string);
        }

        return $string;
    }

    /**
     * Deeply replace all occurrences of the search string with an empty string until no more occurrences are found.
     * This is useful for removing all instances of a substring, even if they are nested or repeated multiple times.
     *
     * @param $search
     * @param string $subject
     * @return string|string[]
     */
    public static function deepReplace($search, string $subject): array|string
    {
        $count = 1;
        $maxIterations = 256; // Prevent infinite loops
        while ($count > 0 && $maxIterations-- > 0) {
            $subject = str_replace($search, '', $subject, $count);
        }
        return $subject;
    }

    /**
     * Sanitize non utf-8 string
     * @param string $data
     * @return string
     */
    public static function encodeFallback(string $data): string
    {
        $regex = '/(
            [\xC0-\xC1] # Invalid UTF-8 Bytes
            | [\xF5-\xFF] # Invalid UTF-8 Bytes
            | \xE0[\x80-\x9F] # Overlong encoding of prior code point
            | \xF0[\x80-\x8F] # Overlong encoding of prior code point
            | [\xC2-\xDF](?![\x80-\xBF]) # Invalid UTF-8 Sequence Start
            | [\xE0-\xEF](?![\x80-\xBF]{2}) # Invalid UTF-8 Sequence Start
            | [\xF0-\xF4](?![\x80-\xBF]{3}) # Invalid UTF-8 Sequence Start
            | (?<=[\x00-\x7F\xF5-\xFF])[\x80-\xBF] # Invalid UTF-8 Sequence Middle
            | (?<![\xC2-\xDF]
                |[\xE0-\xEF]
                |[\xE0-\xEF][\x80-\xBF]
                |[\xF0-\xF4]
                |[\xF0-\xF4][\x80-\xBF]
                |[\xF0-\xF4][\x80-\xBF]{2}
                )[\x80-\xBF] # Overlong Sequence
            | (?<=[\xE0-\xEF])[\x80-\xBF](?![\x80-\xBF]) # Short 3 byte sequence
            | (?<=[\xF0-\xF4])[\x80-\xBF](?![\x80-\xBF]{2}) # Short 4 byte sequence
            | (?<=[\xF0-\xF4][\x80-\xBF])[\x80-\xBF](?![\x80-\xBF]) # Short 4 byte sequence (2)
        )/x';
        return preg_replace_callback(
            $regex,
            static fn($e) => self::pureBinaryFallback($e[1]),
            $data
        );
    }

    /**
     * Fallback method to replace invalid UTF-8 sequences with a best-effort binary representation.
     * This method takes a string that may contain invalid UTF-8 bytes and attempts
     * to convert it into a valid UTF-8 string by treating the bytes as binary data. It handles different byte.
     */
    private static function pureBinaryFallback(string $string): string
    {
        $string .= $string;
        $len = strlen($string);
        for ($i = $len >> 1, $j = 0; $i < $len; ++$i, ++$j) {
            switch (true) {
                case $string[$i] < "\x80":
                    $string[$j] = $string[$i];
                    break;
                case $string[$i] < "\xC0":
                    $string[$j] = "\xC2";
                    $string[++$j] = $string[$i];
                    break;
                default:
                    $string[$j] = "\xC3";
                    $string[++$j] = chr(ord($string[$i]) - 64);
                    break;
            }
        }
        return substr($string, 0, $j);
    }

    /**
     * Check if a string is valid UTF-8
     *
     * @param string $string
     * @return bool
     */
    public static function isUtf8(string $string): bool
    {
        if (self::isMbStringAvailable()) {
            return mb_check_encoding($string, 'UTF-8');
        }

        $length = strlen($string);
        for ($i = 0; $i < $length; $i++) {
            $c = ord($string[$i]);

            // 1-byte (ASCII)
            if ($c < 0x80) {
                continue;
            }

            // 2-byte sequence
            if (($c & 0xE0) === 0xC0) {
                if ($c < 0xC2) {  // Overlong
                    return false;
                }
                if ($i + 1 >= $length || (ord($string[++$i]) & 0xC0) !== 0x80) {
                    return false;
                }
            } elseif (($c & 0xF0) === 0xE0) {
                // 3-byte sequence
                if ($i + 2 >= $length) {
                    return false;
                }
                $c2 = ord($string[++$i]);
                if (($c2 & 0xC0) !== 0x80) {
                    return false;
                }

                // Overlong & Surrogate check
                if ($c === 0xE0 && $c2 < 0xA0) {
                    return false;
                }
                if ($c === 0xED && $c2 >= 0xA0) {
                    return false;
                }

                if ((ord($string[++$i]) & 0xC0) !== 0x80) {
                    return false;
                }
            } elseif (($c & 0xF8) === 0xF0) {
                // 4-byte sequence
                if ($i + 3 >= $length) {
                    return false;
                }
                $c2 = ord($string[++$i]);
                if (($c2 & 0xC0) !== 0x80) {
                    return false;
                }

                // Overlong & Out of range check
                if ($c === 0xF0 && $c2 < 0x90) {
                    return false;
                }
                if ($c === 0xF4 && $c2 >= 0x90) {
                    return false;
                }
                if ($c > 0xF4) {
                    return false;
                }

                if ((ord($string[++$i]) & 0xC0) !== 0x80) {
                    return false;
                }
                if ((ord($string[++$i]) & 0xC0) !== 0x80) {
                    return false;
                }
            } else {
                return false; // Illegal Byte
            }
        }

        return true;
    }

    /**
     * alternative on @param string $string
     * @return string
     * @uses \utf8_decode()
     */
    public static function decode(string $string): string
    {
        if (self::isMbStringAvailable()) {
            return mb_check_encoding($string, 'UTF-8')
                ? $string
                : mb_convert_encoding(
                    $string,
                    mb_detect_encoding($string, null, true) ?: 'ISO-8859-1',
                    'UTF-8'
                );
        }
        $length = strlen($string);
        for ($i = 0, $j = 0; $i < $length; ++$i, ++$j) {
            switch ($string[$i] & "\xF0") {
                case "\xC0":
                case "\xD0":
                    $c = (ord($string[$i] & "\x1F") << 6) | ord($string[++$i] & "\x3F");
                    $string[$j] = $c < 256 ? chr($c) : '?';
                    break;
                case "\xF0":
                    ++$i;
                // no break
                case "\xE0":
                    $string[$j] = '?';
                    $i += 2;
                    break;

                default:
                    $string[$j] = $string[$i];
            }
        }
        return substr($string, 0, $j);
    }

    /**
     * Sanitize Result to UTF-8 , this is recommended to sanitize
     * that result from socket that invalid decode UTF8 values
     *
     * alternative on @param string $string
     * @return string
     * @uses \utf8_encode()
     */
    public static function encode(string $string): string
    {
        if (self::isMbStringAvailable()) {
            return mb_check_encoding($string, 'UTF-8')
                ? $string
                : mb_convert_encoding($string, 'UTF-8', mb_detect_encoding($string, null, true) ?: 'ISO-8859-1');
        }
        if (self::isUtf8($string)) {
            return $string;
        }
        return self::encodeFallback($string);
    }
}
