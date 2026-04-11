<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */
declare(strict_types=1);

namespace GatePay\Core;

use ArrayIterator;
use GatePay\Core\Enum\SourceType;
use GatePay\Core\Exceptions\DataFrozenException;
use GatePay\Core\Interfaces\TransactionResultDataInterface;
use GatePay\Core\Utils\XMLParserArray;
use Traversable;
use function array_key_exists;
use function count;
use function json_decode;
use const JSON_THROW_ON_ERROR;

/**
 * This class represents the result data of a transaction in a payment processing system.
 * It provides methods to manage transaction data, including retrieving, setting, and freezing the data.
 * The transaction data is stored as an associative array,
 * and the class implements the Serializable and
 * Countable interfaces for easy serialization and counting of the data items.
 *
 * @template TKey of array-key
 * @template TValue of mixed
 * @template-implements TransactionResultDataInterface<TKey, TValue>
 */
class TransactionResultData implements TransactionResultDataInterface
{
    /**
     * @var array<TKey, TValue> $data The transaction data stored as an associative array.
     */
    private array $data;

    /**
     * @var bool $frozen Indicates whether the transaction data is frozen (immutable) or not.
     * When frozen, the transaction data cannot be modified.
     */
    private bool $frozen;

    /**
     * @var SourceType $sourceType The source type of the transaction data,
     * which can be used to identify the origin or context of the data.
     * eg: json, xml, array, etc.
     */
    private readonly SourceType $sourceType;

    /**
     * TransactionData constructor.
     * @param SourceType $sourceType The source type of the transaction data,
     * which can be used to identify the origin or context of the data.
     * eg: json, xml, array, etc.
     * @param array<TKey, TValue> $data
     * An optional associative array of transaction data to initialize the object with.
     */
    public function __construct(
        SourceType $sourceType,
        array      $data = [],
        bool       $frozen = false
    ) {
        $this->sourceType = $sourceType;
        $this->data = $data;
        $this->frozen = $frozen;
    }

    /**
     * Create a TransactionResultData instance from a JSON string.
     * @param string $json
     * @return TransactionResultData<TKey, TValue>
     * @throws \JsonException
     */
    public static function fromJson(string $json): TransactionResultData
    {
        $json = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return new self(SourceType::JSON, $json);
    }

    /**
     * Create a TransactionResultData instance from a JSON string.
     * @return TransactionResultData<TKey, TValue>
     * @throws \Throwable
     */
    public static function fromXML(string $xml): TransactionResultData
    {
        return new self(SourceType::XML, XMLParserArray::parse($xml));
    }

    /**
     * @inheritdoc
     */
    public function getSourceType(): SourceType
    {
        return $this->sourceType;
    }

    /**
     * @inheritdoc
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * @inheritdoc
     */
    public function has(int|string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * @inheritdoc
     * @return TValue|null
     */
    public function get(int|string $key): mixed
    {
        if (!$this->has($key)) {
            return null;
        }
        return $this->data[$key];
    }

    /**
     * @inheritdoc
     */
    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    /**
     * @inheritdoc
     * @return TransactionResultData<TKey, TValue>
     */
    public function freeze(): TransactionResultData
    {
        $this->frozen = true;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function set(int|string $key, mixed $value): void
    {
        if ($this->isFrozen()) {
            throw new DataFrozenException();
        }
        $this->data[$key] = $value;
    }

    /**
     * @inheritdoc
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @inheritdoc
     * @return array<TKey, TValue>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /**
     * @inheritdoc
     * @return Traversable<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }
}
