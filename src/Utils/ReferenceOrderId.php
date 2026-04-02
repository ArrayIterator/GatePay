<?php
declare(strict_types=1);

namespace ArrayIterator\GatePay\Utils;

use Throwable;
use function bin2hex;
use function dechex;
use function hash;
use function max;
use function microtime;
use function min;
use function preg_replace;
use function random_bytes;
use function sprintf;
use function str_pad;
use function strtoupper;
use function substr;
use function trim;
use function uniqid;
use const STR_PAD_LEFT;

/**
 * Commonly payment gateway accepted 64 characters or more,
 * but on some providers only accept 30 characters.
 * Generating real random string with 30 characters is good.
 */
class ReferenceOrderId
{
    /**
     * Default prefix for the generated order ID, which can be used if no custom prefix is provided.
     * This default value is set to 'ORDR' to indicate that the generated order ID is an invoice.
     * This can be used with Product Identifier, eg: HSTG, KVMS, JKT1, etc.
     */
    public const string DEFAULT_PREFIX = 'ORDR';

    /**
     * @var non-empty-string $productPrefix
     *
     * Prefix for the generated order ID (always 4 characters).
     * Input will be sanitized: trimmed, uppercased, alphanumeric only.
     * If input < 4 chars, padded with 'X'. If > 4 chars, truncated.
     */
    private string $productPrefix;

    /**
     * Constructor for the ReferenceOrderIdGenerator class.
     *
     * @param string $productPrefix Optional prefix for the generated order ID. If not provided, it defaults to 'INV'.
     * The provided prefix will be formatted to ensure it is exactly 3 characters long, using the formatPrefix method.
     */
    public function __construct(string $productPrefix = self::DEFAULT_PREFIX)
    {
        $this->setProductPrefix($productPrefix);
    }

    /**
     * Formats the provided product prefix to ensure it is exactly 3 characters long.
     *
     * This method trims any whitespace from the input, takes the first 3 characters,
     * and pads it with 'X' if it is less than 4 characters long. This ensures that
     * the resulting prefix is always 4 characters, which is important for maintaining
     * a consistent format for the generated order IDs.
     *
     * @param string $productPrefix The input product prefix to be formatted.
     * @return non-empty-string The formatted product prefix, which is exactly 4 characters long.
     */
    public static function formatPrefix(string $productPrefix): string
    {
        $productPrefix = trim($productPrefix);
        $productPrefix = strtoupper($productPrefix);
        $productPrefix = preg_replace('/[^A-Z0-9]/', '', $productPrefix) ?: '';
        $productPrefix = substr($productPrefix, 0, 4);
        return str_pad($productPrefix, 4, 'X');
    }

    /**
     * Generates a unique order ID by combining the product prefix with a random string.
     *
     * The generated order ID consists of the product prefix followed by a random string of characters.
     * The total length of the generated order ID will not exceed 50 characters, ensuring compatibility
     * with most of the payment gateways. The combination of the product prefix and
     * the random string allows for easy identification of the source or type of the order,
     * while also ensuring that each order ID is unique and difficult
     *
     * @return non-empty-string A unique order ID that combines the product prefix with a random string.
     */
    public function getProductPrefix(): string
    {
        return $this->productPrefix;
    }

    /**
     * Sets the product prefix for the generated order ID.
     *
     * This method allows you to specify a custom prefix for the generated order ID. The provided prefix
     * will be formatted using the formatPrefix method to ensure it is exactly 3 characters long. This
     * allows you to easily identify the source or type of the order based on the prefix used in the order ID.
     *
     * @param string $productPrefix The custom prefix to be set for the generated order ID.
     */
    public function setProductPrefix(string $productPrefix): void
    {
        $this->productPrefix = $this->formatPrefix($productPrefix);
    }

    /**
     * Creates a random string of bytes to be used in the generation of the order ID.
     *
     * This method generates a random string of bytes using the random_bytes function, which provides
     * cryptographically secure random data. The length of the generated string is determined by the
     * input parameter, but it is constrained to be between 1 and 32 bytes to ensure that the resulting
     * order ID does not exceed  the 30 characters limit of many payment gateways when combined with the product prefix.
     * If the random_bytes function is not available, the method falls back to
     * generating a random string using a combination of the current timestamp and a unique identifier,
     * which is then hashed to produce a random string of the desired length. This ensures that even in environments
     *
     * @param int $length The desired length of the random string in bytes.
     * This value will be constrained to be between 1 and 32.
     * @return string A random string of bytes that can be used in the generation of the order ID.
     */
    public function createRandom(int $length): string
    {
        $max_length = 32;
        $min_length = 1;
        $length = max($min_length, min($max_length, $length));
        try {
            return random_bytes($length);
        } catch (Throwable) {
            return substr(
                hash(
                    'sha256',
                    uniqid((string)microtime(true), true),
                    true // raw output to get binary data, which can be truncated to the desired length after hashing
                ),
                0,
                $length
            );
        }
    }

    /**
     * @return string Generates a unique order ID
     *      by combining the product prefix with a random string.
     * The result always 30 characters long,
     *
     * `ORDR-019d43d20eb8-6a5a7925dfb5`
     *
     * ```md
     *
     *  - ORDR-{timestamp hex 12}-{random hex 12}
     *  - 48-bit timestamp (ms) + 48-bit random string = 96 bits = 12 bytes = 24 hex characters
     *  - (N ms) = 2^48 = 281,474,976,710,656
     *  The probability of collision is very low, even with a high volume of orders,
     *  due to the combination of the timestamp and random string.
     * ```
     */
    public function generate(): string
    {
        /* UUID v7 based on the current timestamp
         * 019d43a1-8636-7ff0-ab34-74b31d6a2da2
            │────────────┘
            timestamp (48-bit)
         */
        $ms = (int)(microtime(true) * 1000);
        $ms = $ms & 0xFFFFFFFFFFFF;
        $timestamp = str_pad(dechex($ms), 12, '0', STR_PAD_LEFT); // 12 characters
        // eg: ORDR-019d43d20eb8-7b54a125edb1
        $hex_random = bin2hex($this->createRandom(6)); // 12 char
        $prefix = $this->getProductPrefix(); // 4 char
        return sprintf(
            '%s-%s-%s', // eg : ORDR-019d43d20eb8-6a5a7925dfb5
            $prefix,
            $timestamp,
            $hex_random
        );
    }

    /**
     * Validate if string is valid order ID format
     */
    public static function isValid(string $orderId): bool
    {
        return (bool)preg_match('/^[A-Z0-9]{4}-[0-9a-f]{12}-[0-9a-f]{12}$/', $orderId);
    }

    /**
     * Parse order ID and extract components
     *
     * @return array{prefix: string, timestamp: int, random: string}|null
     *
     * ```php
     *
     * $orderId = 'ORDR-019d43d20eb8-6a5a7925dfb5';
     * $info = ReferenceOrderId::parse($orderId);
     * // [
     * //   'prefix' => 'ORDR',
     * //   'timestamp_ms' => 1735123456789,
     * //   'random' => '6a5a7925dfb5',
     * //   'created_at' => '2024-12-25 12:34:56'
     * // ]
     * ```
     */
    public static function parse(string $orderId): ?array
    {
        if (!self::isValid($orderId)) {
            return null;
        }
        [$prefix, $timestamp_hex, $random_hex] = explode('-', $orderId);
        return [
            'prefix' => $prefix,
            'timestamp' => (int)hexdec($timestamp_hex),
            'timestamp_ms' => (int)hexdec($timestamp_hex),
            'random' => $random_hex,
            'created_at' => date('Y-m-d H:i:s', (int)hexdec($timestamp_hex) / 1000),
        ];
    }

    /**
     * Extract timestamp from order ID
     * @return int|null Timestamp in milliseconds, or null if order ID is invalid
     */
    public static function getTimestamp(string $orderId): ?int
    {
        $parts = self::parse($orderId);
        return $parts['timestamp_ms'] ?? null;
    }

    /**
     * Generate order ID prefix for time range query
     *
     * ```sql
     *
     * SELECT * FROM transactions WHERE order_id LIKE 'ORDR-019d43%'
     *
     * -- Get all ORDR orders from Dec 25-26, 2024
     *
     * SELECT *
     * FROM
     *      transactions
     * WHERE
     *      order_id >= 'ORDR-019d43d20eb8'
     *          AND
     *      order_id < 'ORDR-019d5a3f1234'
     * ```
     */
    public static function getTimeRangePattern(
        string $productPrefix,
        int $fromTimestampMs,
        int $toTimestampMs
    ): array {
        $from = str_pad(dechex($fromTimestampMs & 0xFFFFFFFFFFFF), 12, '0', STR_PAD_LEFT);
        $to = str_pad(dechex($toTimestampMs & 0xFFFFFFFFFFFF), 12, '0', STR_PAD_LEFT);
        $prefix = self::formatPrefix($productPrefix);

        return [
            'from' => "{$prefix}-{$from}",
            'to' => "{$prefix}-{$to}",
            'pattern' => "{$prefix}-%", // For LIKE queries
        ];
    }
}
