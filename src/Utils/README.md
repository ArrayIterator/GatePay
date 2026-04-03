# XML Parser

[XMLParserArray](XMLParserArray.php) is a utility class that provides methods to parse XML strings into associative arrays using three different approaches: LibXML, PureXML, and SimpleXML. Each method offers a unique way to handle XML parsing, allowing developers to choose the one that best suits their needs.

## Parsing Methods

| Method | Description | Requirements |
|--------|-------------|--------------|
| `parse()` | Auto-selects the best available parser | None (fallback to PureXML) |
| `parseSimpleXML()` | Uses PHP's SimpleXML extension | ext-simplexml, ext-libxml |
| `parseLibXML()` | Uses PHP's XML extension | ext-xml, ext-libxml |
| `parsePureXML()` | Pure PHP implementation (no extensions) | None |

## Rules

All three parsers produce **consistent output** following these rules:

### 1. Simple Elements (No Attributes)
Elements without attributes return their text content directly as a string.
```xml
<item>value</item>
```

```php
['item' => 'value']
```

### 2. Self-Closing Tags with Attributes
Self-closing tags with attributes return an array with `@attributes` key containing all attributes, and `@value` key as empty string.
```xml
<item id="1" name="Item 1" />
```
```php
['item' => ['@attributes' => ['id' => '1', 'name' => 'Item 1'], '@value' => '']]
```

### 3. Elements with Attributes and Text Content
Tags with attributes AND text content return `@attributes` and `@value` keys.
```xml
<item id="1">content</item>
```
```php
['item' => ['@attributes' => ['id' => '1'], '@value' => 'content']]
```

### 4. Elements with Attributes and Child Elements
Tags with attributes AND child elements merge `@attributes` with child element keys.
```xml
<item attribute1="value1">
    <id>1</id>
    <name>Item 1</name>
</item>
```
```php
['item' => ['@attributes' => ['attribute1' => 'value1'], 'id' => '1', 'name' => 'Item 1']]
```

### 5. Multiple Same-Name Elements
Multiple elements with the same tag name become an indexed array.
```xml
<root><item>first</item><item>second</item></root>
```
```php
['root' => ['item' => ['first', 'second']]]
```

### Summary Table

| Scenario | `@attributes` | `@value` | Child Keys |
|----------|---------------|---------|------------|
| `<tag />` (no attrs) | ❌ | Returns `''` directly | ❌ |
| `<tag attr="x" />` | ✅ | `''` (empty) | ❌ |
| `<tag>text</tag>` | ❌ | Returns `'text'` directly | ❌ |
| `<tag attr="x">text</tag>` | ✅ | `'text'` | ❌ |
| `<tag attr="x"><child>y</child></tag>` | ✅ | ❌ | ✅ `'child' => 'y'` |
| `<tag><child>y</child></tag>` | ❌ | ❌ | ✅ `'child' => 'y'` |

## Complete Example

```php
<?php
declare(strict_types=1);

use GatePay\Core\Utils\XMLParserArray;

require __DIR__ .'/vendor/autoload.php';

// Example 1: Self-closing tags with attributes
// - Each <item /> has attributes but no content
// - Result: @attributes contains all tag attributes, value is empty string
$xml = <<<XML
<root>
    <item id="1" name="Item 1" price="10.00" />
    <item id="2" name="Item 2" price="20.00" />
    <item id="3" name="Item 3" price="30.00" />
</root>
XML;

// Example 2: Tags with attributes AND child elements
// - Each <item> has attributes AND nested child elements
// - Result: @attributes contains tag attributes, children become separate keys
$xmlNamed = <<<XML
<root>
    <item attribute1="value1" attribute2="value2">
        <id>1</id>
        <name>Item 1</name>
        <price>10.00</price>
    </item>
    <item attribute1="value1" attribute2="value2">
        <id>2</id>
        <name>Item 2</name>
        <price>20.00</price>
    </item>
    <item attribute1="value1" attribute2="value2">
        <id>3</id>
        <name>Item 3</name>
        <price>30.00</price>
    </item>
</root>
XML;

// Expected output for Example 1 (self-closing with attributes):
// - @attributes: contains id, name, price from tag attributes
// - value: empty string (self-closing tag has no content)
$expected = [
    'root' => [
        'item' => [
            ['@attributes' => ['id' => '1', 'name' => 'Item 1', 'price' => '10.00'], '@value' => ''],
            ['@attributes' => ['id' => '2', 'name' => 'Item 2', 'price' => '20.00'], '@value' => ''],
            ['@attributes' => ['id' => '3', 'name' => 'Item 3', 'price' => '30.00'], '@value' => '']
        ]
    ]
];

// Expected output for Example 2 (attributes + child elements):
// - @attributes: contains attribute1, attribute2 from tag attributes
// - id, name, price: child elements become separate keys with their text content
$expectedNamed = [
    'root' => [
        'item' => [
            ['@attributes' => ['attribute1' => 'value1', 'attribute2' => 'value2'], 'id' => '1', 'name' => 'Item 1', 'price' => '10.00'],
            ['@attributes' => ['attribute1' => 'value1', 'attribute2' => 'value2'], 'id' => '2', 'name' => 'Item 2', 'price' => '20.00'],
            ['@attributes' => ['attribute1' => 'value1', 'attribute2' => 'value2'], 'id' => '3', 'name' => 'Item 3', 'price' => '30.00']
        ]
    ]
];

// All three parsers produce identical output
$data = [
    'attributes' => [
        'LibXML' => XMLParserArray::parseLibXML($xml),
        'PureXML' => XMLParserArray::parsePureXML($xml),
        'SimpleXML' => XMLParserArray::parseSimpleXML($xml),
    ],
    'attributes_named' => [
        'LibXML' => XMLParserArray::parseLibXML($xmlNamed),
        'PureXML' => XMLParserArray::parsePureXML($xmlNamed),
        'SimpleXML' => XMLParserArray::parseSimpleXML($xmlNamed),
    ]
];

/*
 * Expected result - all parsers should match:
 *
 * - LibXML matches expected output
 * - PureXML matches expected output
 * - SimpleXML matches expected output
 * - LibXML matches expected output
 * - PureXML matches expected output
 * - SimpleXML matches expected output
 */
foreach ($data as $key => $parsers) {
    $expectedData = match ($key) {
        'attributes' => $expected,
        'attributes_named' => $expectedNamed,
        default => throw new Exception("Unexpected key: $key"),
    };
    foreach ($parsers as $parserName => $result) {
        $matches = $result === $expectedData;
        echo "- $parserName " . ($matches ? "matches" : "does NOT match") . " expected output\n";
        if (!$matches) {
            echo "Result:\n" . print_r($result, true) . "\nExpected:\n" . print_r($expectedData, true) . "\n";
        }
    }
}

```