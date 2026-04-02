<?php
declare(strict_types=1);

namespace ArrayIterator\GatePay\Utils;

use ErrorException;
use ParseError;
use SimpleXMLElement;
use function set_error_handler;

/**
 * Class XMLParserArray
 *
 * A utility class for parsing XML strings and converting them into associative arrays.
 */
class XMLParserArray
{
    /**
     * Parses an XML string and converts it into an associative array.
     *
     * @param string $xml The XML string to be parsed.
     * @return array<array-key, mixed> An associative array representation of the XML data.
     * @throws ErrorException|ParseError If there is an error during parsing the XML string.
     */
    public static function parse(string $xml): array
    {
        try {
            $error = null;
            set_error_handler(function (
                int    $errno,
                string $errStr,
                string $errFile,
                int    $errLine
            ) use (&$error) {
                $error = new ErrorException($errStr, 0, $errno, $errFile, $errLine);
            });
            $xmlObject = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($error !== null) {
                throw $error;
            }
            if ($xmlObject === false) {
                throw new ParseError('Failed to parse XML string.');
            }

            // Include root element
            $rootName = $xmlObject->getName();
            $rootValue = self::nestedParse($xmlObject);
            return [$rootName => $rootValue];
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Nested parsing function to recursively convert SimpleXMLElement objects into arrays.
     *
     * @param SimpleXMLElement $xmlObject
     * @return array<array-key, mixed>|string
     */
    private static function nestedParse(SimpleXMLElement $xmlObject): array|string
    {
        $children = $xmlObject->children();

        if (count($children) === 0) {
            return (string) $xmlObject;
        }

        $data = [];
        foreach ($children as $child) {
            $childName = $child->getName();
            $childValue = self::nestedParse($child);
            if (isset($data[$childName])) {
                if (!is_array($data[$childName]) || !isset($data[$childName][0])) {
                    $data[$childName] = [$data[$childName]];
                }
                $data[$childName][] = $childValue;
            } else {
                $data[$childName] = $childValue;
            }
        }

        return $data;
    }
}
