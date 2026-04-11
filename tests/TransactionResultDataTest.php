<?php
declare(strict_types=1);

namespace GatePay\CoreTests;

use ArrayIterator;
use GatePay\Core\Enum\SourceType;
use GatePay\Core\Exceptions\DataFrozenException;
use GatePay\Core\Interfaces\TransactionResultDataInterface;
use GatePay\Core\TransactionResultData;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransactionResultDataTest extends TestCase
{
    #[Test]
    public function constructorSetsSourceType(): void
    {
        $data = new TransactionResultData(SourceType::JSON);

        $this->assertSame(SourceType::JSON, $data->getSourceType());
    }

    #[Test]
    public function constructorWithDataSetsData(): void
    {
        $inputData = ['key1' => 'value1', 'key2' => 'value2'];
        $data = new TransactionResultData(SourceType::JSON, $inputData);

        $this->assertSame($inputData, $data->all());
    }

    #[Test]
    public function constructorWithEmptyDataSetsEmptyArray(): void
    {
        $data = new TransactionResultData(SourceType::JSON);

        $this->assertSame([], $data->all());
    }

    #[Test]
    public function constructorWithFrozenSetsFrozenState(): void
    {
        $data = new TransactionResultData(SourceType::JSON, [], true);

        $this->assertTrue($data->isFrozen());
    }

    #[Test]
    public function constructorDefaultsToNotFrozen(): void
    {
        $data = new TransactionResultData(SourceType::JSON);

        $this->assertFalse($data->isFrozen());
    }

    #[Test]
    public function fromJsonCreatesInstanceFromValidJson(): void
    {
        $json = '{"name": "John", "age": 30}';
        $data = TransactionResultData::fromJson($json);

        $this->assertInstanceOf(TransactionResultData::class, $data);
        $this->assertSame(SourceType::JSON, $data->getSourceType());
        $this->assertSame('John', $data->get('name'));
        $this->assertSame(30, $data->get('age'));
    }

    #[Test]
    public function fromJsonThrowsExceptionForInvalidJson(): void
    {
        $this->expectException(JsonException::class);

        TransactionResultData::fromJson('invalid json');
    }

    #[Test]
    public function fromJsonHandlesNestedData(): void
    {
        $json = '{"user": {"name": "John", "address": {"city": "NYC"}}}';
        $data = TransactionResultData::fromJson($json);

        $user = $data->get('user');
        $this->assertSame('John', $user['name']);
        $this->assertSame('NYC', $user['address']['city']);
    }

    #[Test]
    public function fromXmlCreatesInstanceFromValidXml(): void
    {
        $xml = '<?xml version="1.0"?><root><name>John</name><age>30</age></root>';
        $data = TransactionResultData::fromXML($xml);

        $this->assertInstanceOf(TransactionResultData::class, $data);
        $this->assertSame(SourceType::XML, $data->getSourceType());
    }

    #[Test]
    public function allReturnsAllData(): void
    {
        $inputData = ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'];
        $data = new TransactionResultData(SourceType::JSON, $inputData);

        $this->assertSame($inputData, $data->all());
    }

    #[Test]
    public function hasReturnsTrueForExistingKey(): void
    {
        $data = new TransactionResultData(SourceType::JSON, ['existing' => 'value']);

        $this->assertTrue($data->has('existing'));
    }

    #[Test]
    public function hasReturnsFalseForNonExistingKey(): void
    {
        $data = new TransactionResultData(SourceType::JSON, ['existing' => 'value']);

        $this->assertFalse($data->has('nonexistent'));
    }

    #[Test]
    public function hasWorksWithNumericKeys(): void
    {
        $data = new TransactionResultData(SourceType::JSON, [0 => 'first', 1 => 'second']);

        $this->assertTrue($data->has(0));
        $this->assertTrue($data->has(1));
        $this->assertFalse($data->has(2));
    }

    #[Test]
    public function getReturnsValueForExistingKey(): void
    {
        $data = new TransactionResultData(SourceType::JSON, ['name' => 'John']);

        $this->assertSame('John', $data->get('name'));
    }

    #[Test]
    public function getReturnsNullForNonExistingKey(): void
    {
        $data = new TransactionResultData(SourceType::JSON, ['name' => 'John']);

        $this->assertNull($data->get('nonexistent'));
    }

    #[Test]
    public function getWorksWithNumericKeys(): void
    {
        $data = new TransactionResultData(SourceType::JSON, [0 => 'first', 1 => 'second']);

        $this->assertSame('first', $data->get(0));
        $this->assertSame('second', $data->get(1));
    }

    #[Test]
    public function isFrozenReturnsFalseByDefault(): void
    {
        $data = new TransactionResultData(SourceType::JSON);

        $this->assertFalse($data->isFrozen());
    }

    #[Test]
    public function freezeSetsDataAsFrozen(): void
    {
        $data = new TransactionResultData(SourceType::JSON);

        $result = $data->freeze();

        $this->assertTrue($data->isFrozen());
        $this->assertSame($data, $result); // Returns self
    }

    #[Test]
    public function setAddsNewValue(): void
    {
        $data = new TransactionResultData(SourceType::JSON);

        $data->set('key', 'value');

        $this->assertSame('value', $data->get('key'));
    }

    #[Test]
    public function setUpdatesExistingValue(): void
    {
        $data = new TransactionResultData(SourceType::JSON, ['key' => 'old']);

        $data->set('key', 'new');

        $this->assertSame('new', $data->get('key'));
    }

    #[Test]
    public function setThrowsExceptionWhenFrozen(): void
    {
        $data = new TransactionResultData(SourceType::JSON);
        $data->freeze();

        $this->expectException(DataFrozenException::class);
        $data->set('key', 'value');
    }

    #[Test]
    public function setWorksWithNumericKeys(): void
    {
        $data = new TransactionResultData(SourceType::JSON);

        $data->set(0, 'first');
        $data->set(1, 'second');

        $this->assertSame('first', $data->get(0));
        $this->assertSame('second', $data->get(1));
    }

    #[Test]
    public function countReturnsZeroForEmptyData(): void
    {
        $data = new TransactionResultData(SourceType::JSON);

        $this->assertSame(0, $data->count());
        $this->assertCount(0, $data);
    }

    #[Test]
    public function countReturnsCorrectNumberOfItems(): void
    {
        $data = new TransactionResultData(SourceType::JSON, ['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertSame(3, $data->count());
        $this->assertCount(3, $data);
    }

    #[Test]
    public function jsonSerializeReturnsAllData(): void
    {
        $inputData = ['name' => 'John', 'age' => 30];
        $data = new TransactionResultData(SourceType::JSON, $inputData);

        $this->assertSame($inputData, $data->jsonSerialize());
    }

    #[Test]
    public function jsonEncodeWorksCorrectly(): void
    {
        $inputData = ['name' => 'John', 'age' => 30];
        $data = new TransactionResultData(SourceType::JSON, $inputData);

        $json = json_encode($data);

        $this->assertSame('{"name":"John","age":30}', $json);
    }

    #[Test]
    public function getIteratorReturnsArrayIterator(): void
    {
        $inputData = ['a' => 1, 'b' => 2];
        $data = new TransactionResultData(SourceType::JSON, $inputData);

        $iterator = $data->getIterator();

        $this->assertInstanceOf(ArrayIterator::class, $iterator);
    }

    #[Test]
    public function canIterateOverData(): void
    {
        $inputData = ['a' => 1, 'b' => 2, 'c' => 3];
        $data = new TransactionResultData(SourceType::JSON, $inputData);

        $result = [];
        foreach ($data as $key => $value) {
            $result[$key] = $value;
        }

        $this->assertSame($inputData, $result);
    }

    #[Test]
    public function supportsAllSourceTypes(): void
    {
        $sourceTypes = SourceType::cases();

        foreach ($sourceTypes as $sourceType) {
            $data = new TransactionResultData($sourceType);
            $this->assertSame($sourceType, $data->getSourceType());
        }
    }

    #[Test]
    public function implementsTransactionResultDataInterface(): void
    {
        $data = new TransactionResultData(SourceType::JSON);

        $this->assertInstanceOf(TransactionResultDataInterface::class, $data);
    }

    #[Test]
    public function fromJsonReturnsUnfrozenData(): void
    {
        $json = '{"name": "John"}';
        $data = TransactionResultData::fromJson($json);

        $this->assertFalse($data->isFrozen());
    }

    #[Test]
    public function fromXmlReturnsUnfrozenData(): void
    {
        $xml = '<?xml version="1.0"?><root><name>John</name></root>';
        $data = TransactionResultData::fromXML($xml);

        $this->assertFalse($data->isFrozen());
    }

    #[Test]
    public function setHandlesNullValue(): void
    {
        $data = new TransactionResultData(SourceType::JSON);

        $data->set('nullable', null);

        $this->assertTrue($data->has('nullable'));
        $this->assertNull($data->get('nullable'));
    }

    #[Test]
    public function setHandlesArrayValue(): void
    {
        $data = new TransactionResultData(SourceType::JSON);

        $data->set('array', ['nested' => 'value']);

        $this->assertSame(['nested' => 'value'], $data->get('array'));
    }

    #[Test]
    public function fromJsonWithEmptyArrayReturnsEmptyData(): void
    {
        $json = '[]';
        $data = TransactionResultData::fromJson($json);

        $this->assertSame([], $data->all());
        $this->assertSame(0, $data->count());
    }

    #[Test]
    public function fromJsonWithEmptyObjectReturnsEmptyData(): void
    {
        $json = '{}';
        $data = TransactionResultData::fromJson($json);

        $this->assertSame([], $data->all());
        $this->assertSame(0, $data->count());
    }

    #[Test]
    public function freezeCanBeCalledMultipleTimes(): void
    {
        $data = new TransactionResultData(SourceType::JSON);

        $data->freeze();
        $data->freeze();
        $data->freeze();

        $this->assertTrue($data->isFrozen());
    }

    #[Test]
    public function hasReturnsTrueForNullValue(): void
    {
        $data = new TransactionResultData(SourceType::JSON, ['nullable' => null]);

        $this->assertTrue($data->has('nullable'));
    }
}
