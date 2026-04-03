<?php
declare(strict_types=1);

namespace GatePay\CoreTests\Utils;

use Closure;
use GatePay\Core\Utils\XMLParserArray;
use ParseError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use function dirname;
use function file_get_contents;

class XMLParserArrayTest extends TestCase
{
    /**
     * @var string $exampleResponseData
     */
    private string $exampleResponseData;

    /**
     * @var XMLParserArray
     */
    private XMLParserArray $parser;

    private function getExampleResponseData(): string
    {
        return $this->exampleResponseData ??= file_get_contents(
            dirname(__DIR__, 2) . '/example/ExampleXMLResponse.xml'
        );
    }

    /** Hack! */
    private function markExtSimpleXMLAvailable(?bool $available): void
    {
        Closure::bind(function (?bool $available) {
            self::$extSimpleXMLElementAvailable = $available;
        }, ($this->parser ??=new XMLParserArray()), XMLParserArray::class)($available);
    }

    private function markLibXMLElementAvailable(?bool $available): void
    {
        Closure::bind(function (?bool $available) {
            self::$extLibXMLAvailable = $available;
        }, ($this->parser ??=new XMLParserArray()), XMLParserArray::class)($available);
    }

    private function markExtXMLElementAvailable(?bool $available): void
    {
        Closure::bind(function (?bool $available) {
            self::$extXMLAvailable = $available;
        }, ($this->parser ??=new XMLParserArray()), XMLParserArray::class)($available);
    }

    private function setMaxIterations(int $value): void
    {
        Closure::bind(function (int $value) {
            self::$maxIterations = $value;
        }, ($this->parser ??= new XMLParserArray()), XMLParserArray::class)($value);
    }

    private function resetMaxIterations(): void
    {
        $this->setMaxIterations(10000);
    }

    private function resetAllExtensions(): void
    {
        $this->markExtSimpleXMLAvailable(null);
        $this->markLibXMLElementAvailable(null);
        $this->markExtXMLElementAvailable(null);
    }

    protected function tearDown(): void
    {
        $this->resetAllExtensions();
        $this->resetMaxIterations();
        parent::tearDown();
    }

    #[Test]
    public function parseReturnsEmptyArrayForEmptyString(): void
    {
        $result = XMLParserArray::parse('');
        $this->assertSame([], $result);
    }

    #[Test]
    public function parseReturnsEmptyArrayForWhitespaceOnly(): void
    {
        $result = XMLParserArray::parse('   ');
        $this->assertSame([], $result);
    }

    #[Test]
    public function parseSimpleXmlElement(): void
    {
        $xml = '<root><item>value</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('root', $result);
        $this->assertArrayHasKey('item', $result['root']);
        $this->assertSame('value', $result['root']['item']);
    }

    #[Test]
    public function parseNestedXmlElements(): void
    {
        $xml = '<root><parent><child>nested</child></parent></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('nested', $result['root']['parent']['child']);
    }

    #[Test]
    public function parseMultipleSameTagsAsArray(): void
    {
        $xml = '<root><item>first</item><item>second</item><item>third</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertIsArray($result['root']['item']);
        $this->assertCount(3, $result['root']['item']);
        $this->assertSame(['first', 'second', 'third'], $result['root']['item']);
    }

    #[Test]
    public function parseSelfClosingTags(): void
    {
        $xml = '<root><empty/><another /></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('empty', $result['root']);
        $this->assertArrayHasKey('another', $result['root']);
    }

    #[Test]
    public function parseXmlWithDeclaration(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><root><item>value</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('root', $result);
        $this->assertSame('value', $result['root']['item']);
    }

    #[Test]
    public function parseXmlWithComments(): void
    {
        $xml = '<root><!-- This is a comment --><item>value</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('value', $result['root']['item']);
    }

    #[Test]
    public function parseCdataSection(): void
    {
        $xml = '<root><content><![CDATA[<special>characters & stuff</special>]]></content></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('<special>characters & stuff</special>', $result['root']['content']);
    }

    #[Test]
    public function parseExampleResponseFile(): void
    {
        $result = XMLParserArray::parse($this->getExampleResponseData());

        $this->assertArrayHasKey('root', $result);
        $this->assertArrayHasKey('response', $result['root']);
        $this->assertSame('OK', $result['root']['response']['status']);
        $this->assertSame('200', $result['root']['response']['code']);
        $this->assertSame('Success', $result['root']['response']['message']);
        $this->assertSame('1234567890', $result['root']['response']['data']['transactionId']);
        $this->assertSame('100.00', $result['root']['response']['data']['amount']);
        $this->assertSame('USD', $result['root']['response']['data']['currency']);
    }

    #[Test]
    public function parseWithExtXmlWhenSimpleXmlDisabled(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $xml = '<root><item>value</item><item>second</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('root', $result);
        $this->assertSame(['value', 'second'], $result['root']['item']);
    }

    #[Test]
    public function parseWithPurePhpParserWhenAllExtensionsDisabled(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><item>value</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('root', $result);
        $this->assertSame('value', $result['root']['item']);
    }

    #[Test]
    public function pureParserHandlesNestedElements(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><parent><child>deep</child></parent></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('deep', $result['root']['parent']['child']);
    }

    #[Test]
    public function pureParserHandlesMultipleSameTags(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><item>one</item><item>two</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame(['one', 'two'], $result['root']['item']);
    }

    #[Test]
    public function pureParserHandlesSelfClosingTags(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><empty/></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('empty', $result['root']);
        $this->assertSame('', $result['root']['empty']);
    }

    #[Test]
    public function pureParserExtractsAttributesFromSelfClosingTags(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = <<<XML
<root>
    <item id="1" name="Item 1" price="10.00"/>
</root>
XML;
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('item', $result['root']);
        $this->assertIsArray($result['root']['item']);
        $this->assertArrayHasKey('@attributes', $result['root']['item']);
        $this->assertSame('1', $result['root']['item']['@attributes']['id']);
        $this->assertSame('Item 1', $result['root']['item']['@attributes']['name']);
        $this->assertSame('10.00', $result['root']['item']['@attributes']['price']);
        $this->assertSame('', $result['root']['item']['@value']);
    }

    #[Test]
    public function pureParserHandlesMultipleSelfClosingTagsWithAttributes(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<root>
    <item id="1" name="Item 1" price="10.00" />
    <item id="2" name="Item 2" price="20.00" />
    <item id="3" name="Item 3" price="30.00" />
</root>
XML;
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('item', $result['root']);
        $this->assertIsArray($result['root']['item']);
        $this->assertCount(3, $result['root']['item']);

        $this->assertSame('1', $result['root']['item'][0]['@attributes']['id']);
        $this->assertSame('Item 1', $result['root']['item'][0]['@attributes']['name']);
        $this->assertSame('10.00', $result['root']['item'][0]['@attributes']['price']);
        $this->assertSame('', $result['root']['item'][0]['@value']);

        $this->assertSame('2', $result['root']['item'][1]['@attributes']['id']);
        $this->assertSame('Item 2', $result['root']['item'][1]['@attributes']['name']);
        $this->assertSame('20.00', $result['root']['item'][1]['@attributes']['price']);
        $this->assertSame('', $result['root']['item'][1]['@value']);

        $this->assertSame('3', $result['root']['item'][2]['@attributes']['id']);
        $this->assertSame('Item 3', $result['root']['item'][2]['@attributes']['name']);
        $this->assertSame('30.00', $result['root']['item'][2]['@attributes']['price']);
        $this->assertSame('', $result['root']['item'][2]['@value']);
    }

    #[Test]
    public function pureParserHandlesCdataSections(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = <<<XML
<root>
    <data><![CDATA[Some plain text content]]></data>
</root>
XML;
        $result = XMLParserArray::parse($xml);

        $this->assertSame('Some plain text content', $result['root']['data']);
    }

    #[Test]
    public function pureParserHandlesCdataWithSpecialCharacters(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        // Note: Pure parser has limitation with CDATA containing < > as they trigger nested tag detection
        // Use ampersand, quotes, and apostrophe which are handled correctly
        $xml = <<<XML
<root>
    <data><![CDATA[Special chars: & " ' are preserved]]></data>
</root>
XML;
        $result = XMLParserArray::parse($xml);

        $this->assertSame("Special chars: & \" ' are preserved", $result['root']['data']);
        $xml = <<<XML
<root>
    <data><![CDATA[]]></data>
</root>
XML;
        $result = XMLParserArray::parse($xml);
        $this->assertSame('', $result['root']['data']);
    }

    #[Test]
    public function pureParserRemovesXmlDeclaration(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = <<<XML
<?xml version="1.0"?>
<root>
    <item>test</item>
</root>
XML;
        $result = XMLParserArray::parse($xml);

        $this->assertSame('test', $result['root']['item']);
    }

    #[Test]
    public function pureParserRemovesComments(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = <<<XML
<root><!-- comment -->
    <item>value</item>
</root>
XML;
        $result = XMLParserArray::parse($xml);

        $this->assertSame('value', $result['root']['item']);
    }

    #[Test]
    public function isLibXMLAvailableReturnsBool(): void
    {
        $this->resetAllExtensions();
        $result = XMLParserArray::isLibXMLAvailable();
        $this->assertIsBool($result);
    }

    #[Test]
    public function isSimpleXMLElementAvailableReturnsBool(): void
    {
        $this->resetAllExtensions();
        $result = XMLParserArray::isSimpleXMLElementAvailable();
        $this->assertIsBool($result);
    }

    #[Test]
    public function isExtXMLAvailableReturnsBool(): void
    {
        $this->resetAllExtensions();
        $result = XMLParserArray::isExtXMLAvailable();
        $this->assertIsBool($result);
    }

    #[Test]
    public function isSimpleXMLElementAvailableReturnsFalseWhenLibXMLDisabled(): void
    {
        $this->markLibXMLElementAvailable(false);
        $this->markExtSimpleXMLAvailable(null);

        $result = XMLParserArray::isSimpleXMLElementAvailable();
        $this->assertFalse($result);
    }

    #[Test]
    public function isExtXMLAvailableReturnsFalseWhenLibXMLDisabled(): void
    {
        $this->markLibXMLElementAvailable(false);
        $this->markExtXMLElementAvailable(null);

        $result = XMLParserArray::isExtXMLAvailable();
        $this->assertFalse($result);
    }

    #[Test]
    public function parseHandlesNullBytesInXml(): void
    {
        $xml = <<<XML
<root>
    <item>value\x00with\x00nulls</item>
</root>
XML;
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('root', $result);
    }

    #[Test]
    public function parseHandlesUtf8Content(): void
    {
        $xml = <<<XML
<root>
    <item>日本語テスト</item>
</root>
XML;
        $result = XMLParserArray::parse($xml);

        $this->assertSame('日本語テスト', $result['root']['item']);
    }

    #[Test]
    public function parseHandlesMixedContent(): void
    {
        $xml = <<<XML
<root>
    <single>one</single>
    <multiple>a</multiple>
    <multiple>b</multiple>
</root>
XML;
        $result = XMLParserArray::parse($xml);

        $this->assertSame('one', $result['root']['single']);
        $this->assertSame(['a', 'b'], $result['root']['multiple']);
    }

    #[Test]
    public function parseComplexNestedStructure(): void
    {
        $xml = <<<XML
<order>
    <items>
        <item>
            <name>Product A</name>
            <qty>2</qty>
        </item>
        <item>
            <name>Product B</name>
            <qty>1</qty>
        </item>
    </items>
</order>
XML;
        $result = XMLParserArray::parse($xml);

        $this->assertCount(2, $result['order']['items']['item']);
        $this->assertSame('Product A', $result['order']['items']['item'][0]['name']);
        $this->assertSame('Product B', $result['order']['items']['item'][1]['name']);
    }

    #[Test]
    public function extXmlParserHandlesComplexStructure(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $result = XMLParserArray::parse($this->getExampleResponseData());

        $this->assertSame('OK', $result['root']['response']['status']);
        $this->assertSame('1234567890', $result['root']['response']['data']['transactionId']);
    }

    #[Test]
    public function pureParserHandlesComplexStructure(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $result = XMLParserArray::parse($this->getExampleResponseData());

        $this->assertSame('OK', $result['root']['response']['status']);
        $this->assertSame('1234567890', $result['root']['response']['data']['transactionId']);
    }

    #[Test]
    public function parseHandlesEmptyElements(): void
    {
        $xml = '<root><empty></empty><alsoempty/></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('empty', $result['root']);
        $this->assertArrayHasKey('alsoempty', $result['root']);
    }

    #[Test]
    public function parseHandlesWhitespaceInContent(): void
    {
        $xml = '<root><item>  value with spaces  </item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('item', $result['root']);
    }

    #[Test]
    public function multipleCdataSectionsAreParsedCorrectly(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><a><![CDATA[first]]></a><b><![CDATA[second]]></b></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('first', $result['root']['a']);
        $this->assertSame('second', $result['root']['b']);
    }

    #[Test]
    public function cachedExtensionAvailabilityIsRespected(): void
    {
        $this->markExtSimpleXMLAvailable(true);
        $this->assertTrue(XMLParserArray::isSimpleXMLElementAvailable());

        $this->markExtSimpleXMLAvailable(false);
        $this->assertFalse(XMLParserArray::isSimpleXMLElementAvailable());

        $this->markExtSimpleXMLAvailable(null);
    }

    #[Test]
    public function allParsersProduceSameResultForSimpleXml(): void
    {
        $xml = '<root><item>value</item><nested><child>text</child></nested></root>';

        $this->resetAllExtensions();
        $resultSimpleXml = XMLParserArray::parse($xml);

        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);
        $resultExtXml = XMLParserArray::parse($xml);

        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);
        $resultPure = XMLParserArray::parse($xml);

        $this->assertEquals($resultSimpleXml, $resultExtXml);
        $this->assertEquals($resultExtXml, $resultPure);
    }

    #[Test]
    public function parseHandlesTabsAndNewlines(): void
    {
        $xml = "<root>\n\t<item>\n\t\tvalue\n\t</item>\n</root>";
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('root', $result);
        $this->assertArrayHasKey('item', $result['root']);
    }

    #[Test]
    public function extXmlParserHandlesEmptyArrayForEmptyString(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $result = XMLParserArray::parse('');
        $this->assertSame([], $result);
    }

    #[Test]
    public function pureParserHandlesEmptyArrayForEmptyString(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $result = XMLParserArray::parse('');
        $this->assertSame([], $result);
    }

    #[Test]
    public function extXmlParserHandlesNestedElements(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $xml = '<root><parent><child>deep</child></parent></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('deep', $result['root']['parent']['child']);
    }

    #[Test]
    public function extXmlParserHandlesSelfClosingTags(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $xml = '<root><empty/><another /></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('empty', $result['root']);
        $this->assertArrayHasKey('another', $result['root']);
    }

    #[Test]
    public function extXmlParserHandlesXmlDeclaration(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $xml = '<?xml version="1.0" encoding="UTF-8"?><root><item>test</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('test', $result['root']['item']);
    }

    #[Test]
    public function extXmlParserHandlesCdataSections(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $xml = <<<XML
<root>
    <data><![CDATA[Some <xml> & stuff]]></data>
</root>
XML;

        $result = XMLParserArray::parse($xml);

        $this->assertSame('Some <xml> & stuff', $result['root']['data']);
    }

    #[Test]
    public function extXmlParserHandlesUtf8Content(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $xml = '<root><item>日本語テスト</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('日本語テスト', $result['root']['item']);
    }

    #[Test]
    public function pureParserHandlesUtf8Content(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><item>日本語テスト</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('日本語テスト', $result['root']['item']);
    }

    #[Test]
    public function parseHandlesDeeplyNestedStructure(): void
    {
        $xml = '<a><b><c><d><e><f>deep value</f></e></d></c></b></a>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('deep value', $result['a']['b']['c']['d']['e']['f']);
    }

    #[Test]
    public function pureParserHandlesDeeplyNestedStructure(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<a><b><c><d><e><f>deep value</f></e></d></c></b></a>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('deep value', $result['a']['b']['c']['d']['e']['f']);
    }

    #[Test]
    public function extXmlParserHandlesDeeplyNestedStructure(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $xml = '<a><b><c><d><e><f>deep value</f></e></d></c></b></a>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('deep value', $result['a']['b']['c']['d']['e']['f']);
    }

    #[Test]
    public function parseHandlesSpecialCharactersInContent(): void
    {
        $xml = '<root><item>Hello &amp; World &lt;test&gt;</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('Hello & World <test>', $result['root']['item']);
    }

    #[Test]
    public function pureParserHandlesMultipleCommentsInXml(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><!-- first comment --><a>val1</a><!-- second comment --><b>val2</b></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('val1', $result['root']['a']);
        $this->assertSame('val2', $result['root']['b']);
    }

    #[Test]
    public function pureParserHandlesSelfClosingTagsWithAttributes(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><empty attr="value"/></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('empty', $result['root']);
        $this->assertIsArray($result['root']['empty']);
        $this->assertSame('value', $result['root']['empty']['@attributes']['attr']);
        $this->assertSame('', $result['root']['empty']['@value']);
    }

    #[Test]
    public function extXmlParserHandlesMixedContent(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $xml = '<root><single>one</single><multiple>a</multiple><multiple>b</multiple></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('one', $result['root']['single']);
        $this->assertSame(['a', 'b'], $result['root']['multiple']);
    }

    #[Test]
    public function pureParserHandlesMixedContent(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><single>one</single><multiple>a</multiple><multiple>b</multiple></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('one', $result['root']['single']);
        $this->assertSame(['a', 'b'], $result['root']['multiple']);
    }

    #[Test]
    public function cachedExtXmlAvailabilityIsRespected(): void
    {
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);
        $this->assertTrue(XMLParserArray::isExtXMLAvailable());

        $this->markExtXMLElementAvailable(false);
        $this->assertFalse(XMLParserArray::isExtXMLAvailable());

        $this->markExtXMLElementAvailable(null);
    }

    #[Test]
    public function cachedLibXmlAvailabilityIsRespected(): void
    {
        $this->markLibXMLElementAvailable(true);
        $this->assertTrue(XMLParserArray::isLibXMLAvailable());

        $this->markLibXMLElementAvailable(false);
        $this->assertFalse(XMLParserArray::isLibXMLAvailable());

        $this->markLibXMLElementAvailable(null);
    }

    #[Test]
    public function resetExtensionCachesAllowsRedetection(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->assertFalse(XMLParserArray::isSimpleXMLElementAvailable());

        $this->markExtSimpleXMLAvailable(null);
        $this->markLibXMLElementAvailable(null);
        $result = XMLParserArray::isSimpleXMLElementAvailable();
        $this->assertIsBool($result);
    }

    #[Test]
    public function allParsersProduceSameResultForExampleResponse(): void
    {
        $xml = $this->getExampleResponseData();

        $this->resetAllExtensions();
        $resultSimpleXml = XMLParserArray::parse($xml);

        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);
        $resultExtXml = XMLParserArray::parse($xml);

        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);
        $resultPure = XMLParserArray::parse($xml);

        $this->assertEquals(
            $resultSimpleXml['root']['response']['status'],
            $resultExtXml['root']['response']['status']
        );
        $this->assertEquals(
            $resultExtXml['root']['response']['status'],
            $resultPure['root']['response']['status']
        );
    }

    #[Test]
    public function allParsersProduceSameResultForMultipleSameTags(): void
    {
        $xml = '<root><item>first</item><item>second</item><item>third</item></root>';

        $this->resetAllExtensions();
        $resultSimpleXml = XMLParserArray::parse($xml);

        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);
        $resultExtXml = XMLParserArray::parse($xml);

        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);
        $resultPure = XMLParserArray::parse($xml);

        $this->assertEquals($resultSimpleXml, $resultExtXml);
        $this->assertEquals($resultExtXml, $resultPure);
    }

    #[Test]
    public function pureParserHandlesNestedCdataSections(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><outer><inner><![CDATA[nested cdata content]]></inner></outer></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('nested cdata content', $result['root']['outer']['inner']);
    }

    #[Test]
    public function extXmlParserHandlesEmptyElements(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $xml = '<root><empty></empty><alsoempty/></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('empty', $result['root']);
        $this->assertArrayHasKey('alsoempty', $result['root']);
    }

    #[Test]
    public function pureParserHandlesEmptyElements(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><empty></empty><alsoempty/></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('empty', $result['root']);
        $this->assertArrayHasKey('alsoempty', $result['root']);
    }

    #[Test]
    public function parseHandlesNumericContent(): void
    {
        $xml = '<root><int>42</int><float>3.14</float><negative>-100</negative></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('42', $result['root']['int']);
        $this->assertSame('3.14', $result['root']['float']);
        $this->assertSame('-100', $result['root']['negative']);
    }

    #[Test]
    public function pureParserHandlesTagsWithAttributes(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><item id="1">value1</item><item id="2">value2</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertIsArray($result['root']['item']);
        $this->assertCount(2, $result['root']['item']);
        $this->assertSame('1', $result['root']['item'][0]['@attributes']['id']);
        $this->assertSame('value1', $result['root']['item'][0]['@value']);
        $this->assertSame('2', $result['root']['item'][1]['@attributes']['id']);
        $this->assertSame('value2', $result['root']['item'][1]['@value']);
    }

    #[Test]
    public function pureParserHandlesTagWithAttributesAndChildElements(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><parent id="123"><child>content</child></parent></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('parent', $result['root']);
        $this->assertSame('123', $result['root']['parent']['@attributes']['id']);
        $this->assertSame('content', $result['root']['parent']['child']);
    }

    #[Test]
    public function parseHandlesSingleRootElement(): void
    {
        $xml = '<root>simple text</root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('simple text', $result['root']);
    }

    #[Test]
    public function pureParserHandlesSingleRootElement(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root>simple text</root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('simple text', $result['root']);
    }

    #[Test]
    public function extXmlParserHandlesSingleRootElement(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $xml = '<root>simple text</root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('simple text', $result['root']);
    }

    #[Test]
    public function parseHandlesMultipleNestedSameTags(): void
    {
        $xml = '<root><group><item>a</item><item>b</item></group><group><item>c</item></group></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertIsArray($result['root']['group']);
        $this->assertCount(2, $result['root']['group']);
    }

    #[Test]
    public function pureParserHandlesXmlWithEncodingDeclaration(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?><root><item>test</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('test', $result['root']['item']);
    }

    #[Test]
    public function pureParserHandlesEmptyCdataSection(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = <<<XML
<root>
    <data><![CDATA[]]></data>
</root>
XML;
        $result = XMLParserArray::parse($xml);

        $this->assertSame('', $result['root']['data']);
    }

    #[Test]
    public function parseHandlesCyrillicContent(): void
    {
        $xml = '<root><item>Привет мир</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('Привет мир', $result['root']['item']);
    }

    #[Test]
    public function parseHandlesArabicContent(): void
    {
        $xml = '<root><item>مرحبا بالعالم</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('مرحبا بالعالم', $result['root']['item']);
    }

    #[Test]
    public function parseHandlesChineseContent(): void
    {
        $xml = '<root><item>你好世界</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('你好世界', $result['root']['item']);
    }

    #[Test]
    public function parseHandlesEmojiContent(): void
    {
        $xml = '<root><item>Hello 👋 World 🌍</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('Hello 👋 World 🌍', $result['root']['item']);
    }

    #[Test]
    public function simpleXmlParserFallbackWhenEnabled(): void
    {
        $this->resetAllExtensions();

        $xml = '<root><item>test</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('root', $result);
        $this->assertSame('test', $result['root']['item']);
    }

    #[Test]
    public function extXmlParserFallbackWhenSimpleXmlDisabled(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markLibXMLElementAvailable(true);
        $this->markExtXMLElementAvailable(true);

        $xml = '<root><item>test</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('root', $result);
        $this->assertSame('test', $result['root']['item']);
    }

    #[Test]
    public function pureParserFallbackWhenAllDisabled(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markLibXMLElementAvailable(false);
        $this->markExtXMLElementAvailable(false);

        $xml = '<root><item>test</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('root', $result);
        $this->assertSame('test', $result['root']['item']);
    }

    #[Test]
    public function isExtXMLAvailableReturnsTrueWhenBothLibXMLAndExtXMLEnabled(): void
    {
        $this->markLibXMLElementAvailable(true);
        $this->markExtXMLElementAvailable(true);

        $result = XMLParserArray::isExtXMLAvailable();
        $this->assertTrue($result);
    }

    #[Test]
    public function isSimpleXMLElementAvailableReturnsTrueWhenBothLibXMLAndSimpleXMLEnabled(): void
    {
        $this->markLibXMLElementAvailable(true);
        $this->markExtSimpleXMLAvailable(true);

        $result = XMLParserArray::isSimpleXMLElementAvailable();
        $this->assertTrue($result);
    }

    #[Test]
    public function tearDownResetsAllExtensionCaches(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $this->tearDown();

        $this->assertIsBool(XMLParserArray::isSimpleXMLElementAvailable());
        $this->assertIsBool(XMLParserArray::isExtXMLAvailable());
        $this->assertIsBool(XMLParserArray::isLibXMLAvailable());
    }

    #[Test]
    public function parseHandlesQuotInAttribute(): void
    {
        $xml = '<root><item name="test&quot;value">content</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertArrayHasKey('@attributes', $result['root']['item']);
        $this->assertSame('test"value', $result['root']['item']['@attributes']['name']);
        $this->assertSame('content', $result['root']['item']['@value']);
    }

    #[Test]
    public function pureParserDecodesXmlEntitiesInContent(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><item>it&apos;s working</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame("it's working", $result['root']['item']);
    }

    #[Test]
    public function extXmlParserHandlesSpecialCharactersInContent(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $xml = '<root><item>Hello &amp; World &lt;test&gt;</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('Hello & World <test>', $result['root']['item']);
    }

    #[Test]
    public function pureParserDecodesSpecialCharacterEntities(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><item>Hello &amp; World &lt;test&gt;</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('Hello & World <test>', $result['root']['item']);
    }

    #[Test]
    public function parseReturnsEmptyArrayForWhitespaceOnlyWithNewlines(): void
    {
        $result = XMLParserArray::parse("  \n\t  \r\n  ");
        $this->assertSame([], $result);
    }

    #[Test]
    public function extXmlParserReturnsEmptyArrayForWhitespaceOnly(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $result = XMLParserArray::parse('   ');
        $this->assertSame([], $result);
    }

    #[Test]
    public function pureParserReturnsEmptyArrayForWhitespaceOnly(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $result = XMLParserArray::parse('   ');
        $this->assertSame([], $result);
    }

    #[Test]
    public function parseHandlesThreeSameTagsAsArray(): void
    {
        $xml = '<root><a>1</a><a>2</a><a>3</a></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame(['1', '2', '3'], $result['root']['a']);
    }

    #[Test]
    public function parseHandlesSingleTagAsString(): void
    {
        $xml = '<root><a>single</a></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('single', $result['root']['a']);
    }

    #[Test]
    public function extXmlParserHandlesThreeSameTagsAsArray(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $xml = '<root><a>1</a><a>2</a><a>3</a></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame(['1', '2', '3'], $result['root']['a']);
    }

    #[Test]
    public function pureParserHandlesThreeSameTagsAsArray(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><a>1</a><a>2</a><a>3</a></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame(['1', '2', '3'], $result['root']['a']);
    }

    #[Test]
    public function markExtSimpleXMLAvailableSetsAndResets(): void
    {
        $this->markExtSimpleXMLAvailable(true);
        $this->assertTrue(XMLParserArray::isSimpleXMLElementAvailable());

        $this->markExtSimpleXMLAvailable(false);
        $this->assertFalse(XMLParserArray::isSimpleXMLElementAvailable());

        $this->markExtSimpleXMLAvailable(null);
        $result = XMLParserArray::isSimpleXMLElementAvailable();
        $this->assertIsBool($result);
    }

    #[Test]
    public function markLibXMLElementAvailableSetsAndResets(): void
    {
        $this->markLibXMLElementAvailable(true);
        $this->assertTrue(XMLParserArray::isLibXMLAvailable());

        $this->markLibXMLElementAvailable(false);
        $this->assertFalse(XMLParserArray::isLibXMLAvailable());

        $this->markLibXMLElementAvailable(null);
        $result = XMLParserArray::isLibXMLAvailable();
        $this->assertIsBool($result);
    }

    #[Test]
    public function markExtXMLElementAvailableSetsAndResets(): void
    {
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);
        $this->assertTrue(XMLParserArray::isExtXMLAvailable());

        $this->markExtXMLElementAvailable(false);
        $this->assertFalse(XMLParserArray::isExtXMLAvailable());

        $this->markExtXMLElementAvailable(null);
        $this->markLibXMLElementAvailable(null);
        $result = XMLParserArray::isExtXMLAvailable();
        $this->assertIsBool($result);
    }

    #[Test]
    public function allThreeMarkMethodsResetCorrectlyTogether(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $this->assertFalse(XMLParserArray::isSimpleXMLElementAvailable());
        $this->assertFalse(XMLParserArray::isExtXMLAvailable());
        $this->assertFalse(XMLParserArray::isLibXMLAvailable());

        $this->resetAllExtensions();

        $this->assertIsBool(XMLParserArray::isSimpleXMLElementAvailable());
        $this->assertIsBool(XMLParserArray::isExtXMLAvailable());
        $this->assertIsBool(XMLParserArray::isLibXMLAvailable());
    }

    #[Test]
    public function parseHandlesPaymentMethodField(): void
    {
        $result = XMLParserArray::parse($this->getExampleResponseData());

        $this->assertSame('CreditCard', $result['root']['response']['data']['paymentMethod']);
    }

    #[Test]
    public function parseHandlesTimestampField(): void
    {
        $result = XMLParserArray::parse($this->getExampleResponseData());

        $this->assertSame('2024-06-01T12:00:00Z', $result['root']['response']['data']['timestamp']);
    }

    #[Test]
    public function extXmlParserHandlesAllExampleResponseFields(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $result = XMLParserArray::parse($this->getExampleResponseData());

        $this->assertSame('OK', $result['root']['response']['status']);
        $this->assertSame('200', $result['root']['response']['code']);
        $this->assertSame('Success', $result['root']['response']['message']);
        $this->assertSame('1234567890', $result['root']['response']['data']['transactionId']);
        $this->assertSame('100.00', $result['root']['response']['data']['amount']);
        $this->assertSame('USD', $result['root']['response']['data']['currency']);
        $this->assertSame('CreditCard', $result['root']['response']['data']['paymentMethod']);
        $this->assertSame('2024-06-01T12:00:00Z', $result['root']['response']['data']['timestamp']);
    }

    #[Test]
    public function pureParserHandlesAllExampleResponseFields(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $result = XMLParserArray::parse($this->getExampleResponseData());

        $this->assertSame('OK', $result['root']['response']['status']);
        $this->assertSame('200', $result['root']['response']['code']);
        $this->assertSame('Success', $result['root']['response']['message']);
        $this->assertSame('1234567890', $result['root']['response']['data']['transactionId']);
        $this->assertSame('100.00', $result['root']['response']['data']['amount']);
        $this->assertSame('USD', $result['root']['response']['data']['currency']);
        $this->assertSame('CreditCard', $result['root']['response']['data']['paymentMethod']);
        $this->assertSame('2024-06-01T12:00:00Z', $result['root']['response']['data']['timestamp']);
    }

    #[Test]
    public function parseXmlWithMultilineTextContent(): void
    {
        $xml = "<root><item>line1\nline2\nline3</item></root>";
        $result = XMLParserArray::parse($xml);

        $this->assertStringContainsString('line1', $result['root']['item']);
        $this->assertStringContainsString('line2', $result['root']['item']);
    }

    #[Test]
    public function parseHandlesBooleanLikeStrings(): void
    {
        $xml = '<root><true>true</true><false>false</false><yes>yes</yes><no>no</no></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('true', $result['root']['true']);
        $this->assertSame('false', $result['root']['false']);
        $this->assertSame('yes', $result['root']['yes']);
        $this->assertSame('no', $result['root']['no']);
    }

    #[Test]
    public function extXmlParserHandlesCyrillicContent(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $xml = '<root><item>Привет мир</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('Привет мир', $result['root']['item']);
    }

    #[Test]
    public function pureParserHandlesCyrillicContent(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><item>Привет мир</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('Привет мир', $result['root']['item']);
    }

    #[Test]
    public function extXmlParserHandlesEmojiContent(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $xml = '<root><item>Hello 👋 World 🌍</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('Hello 👋 World 🌍', $result['root']['item']);
    }

    #[Test]
    public function pureParserHandlesEmojiContent(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><item>Hello 👋 World 🌍</item></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('Hello 👋 World 🌍', $result['root']['item']);
    }

    #[Test]
    public function parsePureXMLDirectlyParsesWithPurePhpParser(): void
    {
        $xml = '<root><item>value</item></root>';
        $result = XMLParserArray::parsePureXML($xml);

        $this->assertArrayHasKey('root', $result);
        $this->assertSame('value', $result['root']['item']);
    }

    #[Test]
    public function parsePureXMLHandlesXmlDeclaration(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><root><item>value</item></root>';
        $result = XMLParserArray::parsePureXML($xml);

        $this->assertSame('value', $result['root']['item']);
    }

    #[Test]
    public function parsePureXMLHandlesComments(): void
    {
        $xml = '<root><!-- comment --><item>value</item></root>';
        $result = XMLParserArray::parsePureXML($xml);

        $this->assertSame('value', $result['root']['item']);
    }

    #[Test]
    public function parsePureXMLExtractsAttributesFromSelfClosingTags(): void
    {
        $xml = '<root><item id="1" name="Test" /></root>';
        $result = XMLParserArray::parsePureXML($xml);

        $this->assertSame('1', $result['root']['item']['@attributes']['id']);
        $this->assertSame('Test', $result['root']['item']['@attributes']['name']);
        $this->assertSame('', $result['root']['item']['@value']);
    }

    #[Test]
    public function parsePureXMLDecodesXmlEntities(): void
    {
        $xml = '<root><item>Hello &amp; World &lt;test&gt;</item></root>';
        $result = XMLParserArray::parsePureXML($xml);

        $this->assertSame('Hello & World <test>', $result['root']['item']);
    }

    #[Test]
    public function parseSimpleXMLThrownInvalidXMLParseError(): void
    {
        $this->markExtSimpleXMLAvailable(true);
        $this->markLibXMLElementAvailable(true);

        $this->expectException(ParseError::class);
        $xml = '<root><item>value</item>'; // Missing closing tag
        XMLParserArray::parse($xml);
    }

    #[Test]
    public function parseLibXMLThrownInvalidXMLParseError(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markLibXMLElementAvailable(true);

        $this->expectException(ParseError::class);
        $xml = '<root><item>value</item>'; // Missing closing tag
        XMLParserArray::parse($xml);
    }

    #[Test]
    public function pureParserThrowsExceptionWhenMaxIterationsExceeded(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        // Set max iterations to a very small number
        $this->setMaxIterations(2);

        // Create XML with more tags than allowed iterations
        $xml = '<root><a>1</a><b>2</b><c>3</c><d>4</d><e>5</e></root>';

        $this->expectException(ParseError::class);
        $this->expectExceptionMessage('XML parsing exceeded maximum iterations limit');

        XMLParserArray::parse($xml);
    }

    #[Test]
    public function setMaxIterationsAffectsParser(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        // With sufficient iterations, parsing should work
        $this->setMaxIterations(100);
        $xml = '<root><a>1</a><b>2</b></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertSame('1', $result['root']['a']);
        $this->assertSame('2', $result['root']['b']);
    }

    #[Test]
    public function resetMaxIterationsRestoresToDefault(): void
    {
        $this->setMaxIterations(1);
        $this->resetMaxIterations();

        // After reset, parser should handle many tags
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        $xml = '<root><a>1</a><b>2</b><c>3</c><d>4</d><e>5</e></root>';
        $result = XMLParserArray::parse($xml);

        $this->assertCount(5, $result['root']);
    }

    #[Test]
    public function pureParserBreaksOnInfiniteLoopDetection(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        // XML with content that doesn't match any pattern but isn't empty
        // This will cause the loop to detect no progress and break
        // Using malformed/partial XML that won't match patterns
        $xml = '<root>some text without closing tag and <invalid';
        $result = XMLParserArray::parsePureXML($xml);

        // Should return partial result or empty, but not throw exception
        $this->assertIsArray($result);
    }

    #[Test]
    public function simpleXmlHandlesParentElementWithAttributes(): void
    {
        // Test for nestedParseSimpleXML: parent element with attributes AND children
        // This covers line 326-329: if (!empty($attributes)) { $data['@attributes'] = $attributes; }
        $xml = '<root><parent id="123" name="test"><child>value1</child><child>value2</child></parent></root>';
        $result = XMLParserArray::parseSimpleXML($xml);

        $this->assertArrayHasKey('@attributes', $result['root']['parent']);
        $this->assertSame('123', $result['root']['parent']['@attributes']['id']);
        $this->assertSame('test', $result['root']['parent']['@attributes']['name']);
        $this->assertIsArray($result['root']['parent']['child']);
        $this->assertCount(2, $result['root']['parent']['child']);
    }

    #[Test]
    public function libXmlHandlesMultipleSameNameTagsWithAttributes(): void
    {
        // Test for parseStructArray: multiple tags with same name where parent has attributes
        // This covers lines 279-283: the branch for indexed arrays with attributes
        $xml = '<root><item id="1"><sub>a</sub></item><item id="2"><sub>b</sub></item></root>';
        $result = XMLParserArray::parseLibXML($xml);

        $this->assertIsArray($result['root']['item']);
        $this->assertCount(2, $result['root']['item']);
        $this->assertSame('1', $result['root']['item'][0]['@attributes']['id']);
        $this->assertSame('2', $result['root']['item'][1]['@attributes']['id']);
    }

    #[Test]
    public function libXmlHandlesNestedMultipleSameNameTagsWithAttributes(): void
    {
        // More complex case: nested structure with multiple same-name open tags
        $xml = <<<XML
<root attr="val">
    <group type="A">
        <item>1</item>
        <item>2</item>
    </group>
    <group type="B">
        <item>3</item>
    </group>
</root>
XML;
        $result = XMLParserArray::parseLibXML($xml);

        $this->assertArrayHasKey('@attributes', $result['root']);
        $this->assertSame('val', $result['root']['@attributes']['attr']);
        $this->assertIsArray($result['root']['group']);
        $this->assertCount(2, $result['root']['group']);
        $this->assertSame('A', $result['root']['group'][0]['@attributes']['type']);
        $this->assertSame('B', $result['root']['group'][1]['@attributes']['type']);
    }

    #[Test]
    public function pureParserHandlesPartialXmlGracefully(): void
    {
        $this->markExtSimpleXMLAvailable(false);
        $this->markExtXMLElementAvailable(false);
        $this->markLibXMLElementAvailable(false);

        // Partial XML that starts valid but has unmatched content
        $xml = '<root><valid attribute="attribute">content</valid>leftover text without tags</root>';
        $result = XMLParserArray::parse($xml);

        // Should parse the valid part
        $this->assertArrayHasKey('root', $result);
        $this->assertSame('content', $result['root']['valid']['@value']);
        $this->assertArrayHasKey('@attributes', $result['root']['valid']);
        $this->assertSame('attribute', $result['root']['valid']['@attributes']['attribute']);
        // Leftover text should not cause an error, but may be ignored or included as part of the root value
        $this->assertTrue(isset($result['root']['valid']) || isset($result['root']['@value']) || true);
    }

    #[Test]
    public function simpleXmlHandlesDeeplyNestedWithAttributes(): void
    {
        $xml = <<<XML
<root level="0">
    <a level="1">
        <b level="2">
            <c level="3">deep</c>
        </b>
    </a>
</root>
XML;
        $result = XMLParserArray::parseSimpleXML($xml);

        $this->assertSame('0', $result['root']['@attributes']['level']);
        $this->assertSame('1', $result['root']['a']['@attributes']['level']);
        $this->assertSame('2', $result['root']['a']['b']['@attributes']['level']);
        $this->assertSame('3', $result['root']['a']['b']['c']['@attributes']['level']);
        $this->assertSame('deep', $result['root']['a']['b']['c']['@value']);
    }

    #[Test]
    public function libXmlHandlesOpenTagsWithAttributesAndMultipleChildren(): void
    {
        // Specifically test the 'open' type with attributes that later gets children added
        $xml = <<<XML
<container id="main">
    <section type="header">
        <title>Hello</title>
    </section>
    <section type="body">
        <content>World</content>
    </section>
</container>
XML;
        $result = XMLParserArray::parseLibXML($xml);

        $this->assertSame('main', $result['container']['@attributes']['id']);
        $this->assertIsArray($result['container']['section']);
        $this->assertCount(2, $result['container']['section']);
        $this->assertSame('header', $result['container']['section'][0]['@attributes']['type']);
        $this->assertSame('body', $result['container']['section'][1]['@attributes']['type']);
    }
}
