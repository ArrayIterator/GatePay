<?php
declare(strict_types=1);

namespace GatePay\Core\Interfaces;

use Countable;
use GatePay\Core\Enum\SourceType;
use IteratorAggregate;
use JsonSerializable;
use Serializable;

/**
 * This interface defines the structure for transaction result data in a payment processing system.
 * It provides methods to manage transaction data, including retrieving all data, checking for the existence of
 * specific keys, getting values associated with keys, and managing the immutability of the data through freezing.
 * The interface extends Serializable and Countable, allowing for easy serialization of the transaction data
 * and counting the number of data items it contains. Implementing classes must provide concrete implementations
 * for all defined methods,
 * ensuring consistent handling of transaction result data across the payment processing system.
 *
 * @template TKey of array-key
 * @template TValue
 * @template-extends IteratorAggregate<TKey, TValue>
 */
interface TransactionResultDataInterface extends Serializable, Countable, JsonSerializable, IteratorAggregate
{
    /**
     * Get the source type of the transaction data.
     *
     * @return SourceType Returns a string representing the source type of the transaction data.
     * The source type can be used to identify the origin or context of the data,
     * such as "json", "xml", "array", etc.
     */
    public function getSourceType() : SourceType;

    /**
     * Retrieve all transaction data as an associative array.
     *
     * @return array<TKey, TValue> An associative array containing all transaction data,
     *      where the keys are of type TKey and the values are of mixed type.
     */
    public function all() : array;

    /**
     * Determine if the specified key exists in the transaction data.
     *
     * @param TKey $key The key to retrieve from the transaction data.
     * @return bool Returns true if the specified key exists
     * in the transaction data, false otherwise.
     */
    public function has(int|string $key) : bool;

    /**
     * Get the value associated with the specified key from the transaction data.
     * If the key does not exist, this method may return null or throw an exception,
     * depending on the implementation.
     *
     * @param TKey $key The key to retrieve from the transaction data.
     * @return TValue The value associated with the specified key, or null if the key does
     *      not exist in the transaction data.
     */
    public function get(int|string $key) : mixed;

    /**
     * Determine if the transaction data is frozen.
     *
     * @return bool Returns true if the transaction data is frozen, false otherwise.
     */
    public function isFrozen() : bool;

    /**
     * Freeze the transaction data, making it immutable. Once frozen, the transaction data cannot be modified.
     *
     * @return TransactionResultDataInterface
     */
    public function freeze() : TransactionResultDataInterface;

    /**
     * Set a value in the transaction data.
     *
     * @param TKey $key The key to set in the transaction data.
     * @param TValue $value The value to associate with the specified key.
     * @return void
     * @throws \GatePay\Core\Exceptions\DataFrozenException
     * If the transaction data is frozen and cannot be modified.
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function set(int|string $key, mixed $value) : void;
}
