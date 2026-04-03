<?php
/** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

namespace GatePay\Core\Utils;

use ParseError;
use function array_pop;
use function class_exists;
use function count;
use function extension_loaded;
use function function_exists;
use function is_array;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function restore_error_handler;
use function simplexml_load_string;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;
use function trim;
use function uniqid;
use function xml_error_string;
use function xml_get_error_code;
use function xml_parse_into_struct;
use function xml_parser_create;
use function xml_parser_free;
use function xml_parser_set_option;
use const LIBXML_NOCDATA;
use const PREG_SET_ORDER;
use const XML_OPTION_CASE_FOLDING;
use const XML_OPTION_SKIP_WHITE;

/**
 * Class XMLParserArray
 *
 * A utility class for parsing XML strings and converting them into associative arrays.
 */
class XMLParserArray
{
    /**
     * @var bool|null $extSimpleXMLElementAvailable
     * A static property to cache the availability of the SimpleXML extension.
     * This is used to optimize performance by avoiding repeated checks for the extension's availability.
     */
    private static ?bool $extSimpleXMLElementAvailable = null;

    /**
     * @var bool|null $extXMLAvailable
     * A static property to cache the availability of the XML extension.
     * This is used to optimize performance by avoiding repeated checks for the extension's availability.
     */
    private static ?bool $extXMLAvailable = null;

    /**
     * @var bool|null $extLibXMLAvailable
     * A static property to cache the availability of the libxml extension.
     * This is used to optimize performance by avoiding repeated checks for the extension's availability.
     */
    private static ?bool $extLibXMLAvailable = null;

    /**
     * @var array<string, string> $cdataStore A static array to store CDATA content temporarily during parsing.
     */
    private static array $cdataStore = [];

    /**
     * @var string $currentCDATAPlaceholder
     * A static string to generate unique placeholders for CDATA sections.
     */
    private static string $currentCDATAPlaceholder = '';

    /**
     * @var int $maxIterations
     * Maximum iterations for the pure XML parser to prevent infinite loops.
     * Can be modified for testing purposes.
     */
    private static int $maxIterations = 10000;

    /**
     * Check if libxml extension is available
     * This method checks for the availability of the libxml extension,
     * which is a core dependency for XML parsing in PHP.
     *
     * @return bool
     */
    public static function isLibXMLAvailable(): bool
    {
        return self::$extLibXMLAvailable ??= extension_loaded('libxml');
    }

    /**
     * Check if SimpleXMLElement (ext-simplexml) available
     *
     * @return bool
     */
    public static function isSimpleXMLElementAvailable(): bool
    {
        return self::$extSimpleXMLElementAvailable ??= self::isLibXMLAvailable()
            && extension_loaded('simplexml')
            && function_exists('simplexml_load_string')
            && class_exists('SimpleXMLElement');
    }

    /**
     * Check if XML extension is available
     * This method checks for the availability of the XML extension and its required functions.
     * @return bool
     */
    public static function isExtXMLAvailable(): bool
    {
        return self::$extXMLAvailable ??= self::isLibXMLAvailable()
            && extension_loaded('xml')
            && function_exists('xml_parser_create')
            && function_exists('xml_set_element_handler')
            && function_exists('xml_set_character_data_handler')
            && function_exists('xml_parse')
            && function_exists('xml_parser_free');
    }

    /**
     * Parse response
     *
     * @param string $xml
     * @return array<array-key, mixed>
     * @throws \Throwable
     */
    public static function parse(string $xml): array
    {
        try {
            return self::internalParseXML($xml);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Internal method to parse XML string into an associative array.
     * This method first checks for the availability of the SimpleXML extension and uses it if available.
     * If SimpleXML is not available, it falls back to using the XML extension.
     * If neither extension is available, it uses a pure PHP implementation to parse the XML string.
     *
     * @param string $xml
     * @return array<array-key, mixed>
     * @throws ParseError
     */
    private static function internalParseXML(string $xml): array
    {
        if (self::isSimpleXMLElementAvailable()) {
            return self::parseSimpleXML($xml);
        }

        if (self::isExtXMLAvailable()) {
            return self::parseLibXML($xml);
        }

        return self::parsePureXML($xml);
    }

    /**
     * Parse XML string using SimpleXML extension.
     * This method loads the XML string into a SimpleXMLElement object and then converts it into an associative array.
     *
     * @param string $xml
     * @return array<array-key, mixed>
     * @throws ParseError If the XML string cannot be parsed by SimpleXML.
     */
    public static function parseSimpleXML(string $xml): array
    {
        $xml = Utf8::replaceNullString($xml);
        $xml = trim(Utf8::encode($xml));
        if ($xml === '') {
            return [];
        }
        $xmlObject = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xmlObject === false) {
            throw new ParseError('Failed to parse XML string via SimpleXML.');
        }
        $rootName = $xmlObject->getName();
        return [$rootName => self::nestedParseSimpleXML($xmlObject)];
    }

    /**
     * Parse XML string using SimpleXML extension.
     * This method loads the XML string into a SimpleXMLElement object and then converts it into an associative array.
     *
     * @param string $xml
     * @return array<array-key, mixed>
     * @throws ParseError If the XML string cannot be parsed by SimpleXML.
     */
    public static function parseLibXML(string $xml) : array
    {
        $xml = Utf8::replaceNullString($xml);
        $xml = trim(Utf8::encode($xml));
        if ($xml === '') {
            return [];
        }
        $parser = xml_parser_create('UTF-8');
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);

        $values = [];
        $index = [];
        if (!@xml_parse_into_struct($parser, $xml, $values, $index)) {
            $error = xml_error_string(xml_get_error_code($parser));
            xml_parser_free($parser);
            throw new ParseError('Failed to parse XML: ' . $error);
        }
        xml_parser_free($parser);
        return self::parseStructArray($values);
    }

    /**
     * Parse XML string using a pure PHP implementation.
     * This method uses a custom algorithm to parse the XML string without relying
     * on any external libraries or extensions.
     *
     * @param string $xml
     * @return array<array-key, mixed>
     * @throws ParseError If the XML string cannot be parsed by the pure PHP implementation.
     */
    public static function parsePureXML(string $xml): array
    {
        $xml = Utf8::replaceNullString($xml);
        $xml = trim(Utf8::encode($xml));
        if ($xml === '') {
            return [];
        }
        $xml = preg_replace('/<!--.*?-->/s', '', $xml); // Remove XML comments
        // clean up XML string by removing XML declaration and comments to prevent parsing issues
        $xml = preg_replace('/<\?xml[^>]*\?>/i', '', $xml);
        return self::pureXMLParser($xml);
    }

    /**
     * Helper method to convert the output of xml_parse_into_struct into a nested associative array,
     * while handling multiple occurrences of the same tag name as an array of values.
     *
     * For tags with attributes:
     * - Attributes are stored in '@attributes' key
     * - Text content is stored in '@value' key
     * - Child elements are stored with their tag names as keys
     *
     * @param array $values
     * @return array
     */
    private static function parseStructArray(array $values): array
    {
        $arr = [];
        $stack = [&$arr];

        foreach ($values as $val) {
            $type = $val['type'];
            $name = $val['tag'];
            $textValue = $val['value'] ?? '';
            $attributes = $val['attributes'] ?? [];

            if ($type === 'open' || $type === 'complete') {
                $current = &$stack[count($stack) - 1];

                // Build the element value
                if ($type === 'complete') {
                    if (!empty($attributes)) {
                        $elementValue = ['@attributes' => $attributes, '@value' => $textValue];
                    } else {
                        $elementValue = $textValue;
                    }
                } else {
                    // 'open' type - will have children
                    if (!empty($attributes)) {
                        $elementValue = ['@attributes' => $attributes];
                    } else {
                        $elementValue = [];
                    }
                }

                if (!isset($current[$name])) {
                    $current[$name] = $elementValue;
                } else {
                    if (!is_array($current[$name]) || !isset($current[$name][0])) {
                        $current[$name] = [$current[$name]];
                    }
                    $current[$name][] = $elementValue;
                }

                if ($type === 'open') {
                    if (is_array($current[$name]) && isset($current[$name][0])) {
                        $stack[] = &$current[$name][count($current[$name]) - 1];
                    } else {
                        $stack[] = &$current[$name];
                    }
                }
            } elseif ($type === 'close') {
                array_pop($stack);
            }
        }
        return $arr;
    }

    /**
     * Exclusive helper For SimpleXML parsing, recursively converts SimpleXMLElement objects into arrays,
     * while handling multiple occurrences of the same tag name as an array of values.
     *
     * For tags with attributes:
     * - Attributes are stored in '@attributes' key
     * - Text content is stored in '@value' key
     * - Child elements are stored with their tag names as keys
     *
     * @param \SimpleXMLElement $xmlObject
     * @return array|string Returns a string if the XML element has no children and no attributes,
     *                      or an array if it has child elements or attributes.
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    private static function nestedParseSimpleXML(\SimpleXMLElement $xmlObject): array|string
    {
        $children = $xmlObject->children();
        $attributes = [];

        // Extract attributes
        foreach ($xmlObject->attributes() as $attrName => $attrValue) {
            $attributes[(string)$attrName] = (string)$attrValue;
        }

        if (count($children) === 0) {
            // No children - just text content
            $textValue = (string) $xmlObject;
            if (!empty($attributes)) {
                return ['@attributes' => $attributes, '@value' => $textValue];
            }
            return $textValue;
        }

        // Has children
        $data = [];
        if (!empty($attributes)) {
            $data['@attributes'] = $attributes;
        }

        foreach ($children as $child) {
            $childName = $child->getName();
            $childValue = self::nestedParseSimpleXML($child);

            self::getArrXML($data, $childName, $childValue);
        }
        return $data;
    }

    /**
     * Insert element into target array,
     * handling multiple occurrences of the same tag name as an array of values.
     */
    private static function getArrXML(array &$target, string $name, $element): void
    {
        if (isset($target[$name])) {
            if (!is_array($target[$name]) || !isset($target[$name][0])) {
                $target[$name] = [$target[$name]];
            }
            $target[$name][] = $element;
        } else {
            $target[$name] = $element;
        }
    }

    /**
     * Parse attributes from an XML tag attribute string.
     * Extracts key="value" or key='value' pairs from the attribute portion of an XML tag.
     *
     * @param string $attrString The attribute string (e.g., 'id="1" name="Item 1"')
     * @return array<string, string> Associative array of attribute name => value
     */
    private static function parseAttributes(string $attrString): array
    {
        $attributes = [];
        $attrString = trim($attrString);

        if ($attrString === '') {
            return $attributes;
        }

        // Match attributes in format: name="value" or name='value'
        if (preg_match_all(
            '/([a-zA-Z_:][\w:.-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/s',
            $attrString,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $attrName = $match[1];
                // Value is in group 2 (double quotes) or group 3 (single quotes)
                $attrValue = $match[2] !== '' ? $match[2] : ($match[3] ?? '');
                // Decode common XML entities
                $attrValue = self::decodeXmlEntities($attrValue);
                $attributes[$attrName] = $attrValue;
            }
        }

        return $attributes;
    }

    /**
     * Decode common XML entities in a string.
     *
     * @param string $value
     * @return string
     */
    private static function decodeXmlEntities(string $value): string
    {
        return str_replace(
            ['&lt;', '&gt;', '&amp;', '&apos;', '&quot;'],
            ['<', '>', '&', "'", '"'],
            $value
        );
    }

    /**
     * Pure simple XML parser that converts an XML string into an associative array.
     * It uses a binary string cutting algorithm to parse the XML
     * without relying on any external libraries or extensions.
     *
     * For tags with attributes:
     * - Attributes are stored in '@attributes' key
     * - Text content is stored in '@value' key
     * - Child elements are stored with their tag names as keys
     *
     * @param string $xml
     * @return array
     */
    private static function pureXMLParser(string $xml): array
    {
        $data = [];
        $xml = trim($xml);

        $iteration = 0;
        $previousLength = null;

        while ($xml !== '' && $iteration < self::$maxIterations) {
            $iteration++;
            $currentLength = strlen($xml);

            // Detect infinite loop - if length hasn't changed
            if ($previousLength === $currentLength) {
                break;
            }
            $previousLength = $currentLength;

            // Pre-process CDATA sections before parsing
            $xmlProcessed = self::processCDATA($xml);

            // find the first tag and its content using regex (anchor to start with ^)
            if (preg_match('/^<([^>\/\s]+)([^>]*)>(.*?)<\/\1>/s', $xmlProcessed, $matches)) {
                $tagName = $matches[1];
                $attrString = $matches[2];
                $innerContentProcessed = $matches[3]; // Still has CDATA placeholders
                $fullMatch = $matches[0];

                // Parse attributes
                $attributes = self::parseAttributes($attrString);

                // Check if inner content contains nested tags BEFORE restoring CDATA
                // This prevents CDATA content with <> from being treated as nested tags
                if (preg_match('/<[^>]+>/', $innerContentProcessed)) {
                    // Restore CDATA content before recursive parsing
                    $innerContent = self::restoreCDATA($innerContentProcessed);
                    $childData = self::pureXMLParser($innerContent);
                    // Merge attributes with child elements
                    if (!empty($attributes)) {
                        $value = ['@attributes' => $attributes] + $childData;
                    } else {
                        $value = $childData;
                    }
                } else {
                    // Text content only - restore CDATA and decode entities
                    $innerContent = self::restoreCDATA($innerContentProcessed);
                    $textValue = self::decodeXmlEntities(trim($innerContent));
                    if (!empty($attributes)) {
                        $value = ['@attributes' => $attributes, '@value' => $textValue];
                    } else {
                        $value = $textValue;
                    }
                }

                self::getArrXML($data, $tagName, $value);

                // Remove the matched portion from the original XML (not processed)
                $xml = substr($xml, strlen($fullMatch));
                $xml = trim($xml);
            } elseif (preg_match('/^<([^>\/\s]+)([^>]*)\/>/s', $xmlProcessed, $matches)) {
                // Try to match self-closing tags
                $tagName = $matches[1];
                $attrString = $matches[2];
                $fullMatch = $matches[0];

                // Parse attributes from self-closing tag
                $attributes = self::parseAttributes($attrString);

                // Self-closing tags: use @attributes and empty value
                if (!empty($attributes)) {
                    $value = ['@attributes' => $attributes, '@value' => ''];
                } else {
                    $value = '';
                }

                self::getArrXML($data, $tagName, $value);

                $xml = substr($xml, strlen($fullMatch));
                $xml = trim($xml);
            } else {
                // stop if no more tags are found to prevent infinite loop
                break;
            }
        }

        if ($iteration >= self::$maxIterations) {
            throw new ParseError('XML parsing exceeded maximum iterations limit');
        }

        return $data;
    }

    /**
     * Process CDATA sections by replacing them with placeholders
     */
    private static function processCDATA(string $xml): string
    {
        self::$cdataStore = []; // Reset store
        self::$currentCDATAPlaceholder = sprintf(
            '___CDATA_%s__',
            preg_replace(
                '~[^0-9a-zA-Z]~',
                '_',
                uniqid('', true)
            )
        );
        /** @noinspection RegExpRedundantEscape */
        return preg_replace_callback(
            '~<!\[CDATA\[(.*?)\]\]>~s',
            function ($matches) {
                $placeholder = self::$currentCDATAPlaceholder . count(self::$cdataStore) . '___';
                self::$cdataStore[$placeholder] = $matches[1];
                return $placeholder;
            },
            $xml
        );
    }

    /**
     * Restore CDATA content from placeholders
     */
    private static function restoreCDATA(string $content): string
    {
        if (empty(self::$currentCDATAPlaceholder)
            || empty(self::$cdataStore)
        ) {
            return $content; // No CDATA content to restore
        }

        $placeholder = preg_quote(self::$currentCDATAPlaceholder, '~'); // Escape special chars
        $data = preg_replace_callback(
            "~{$placeholder}(\d+)___~",
            function ($matches) {
                $placeholder = $matches[0];
                return self::$cdataStore[$placeholder] ?? $placeholder;
            },
            $content
        );
        self::$currentCDATAPlaceholder = '';
        return $data;
    }
}
