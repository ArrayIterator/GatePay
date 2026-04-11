<?php
declare(strict_types=1);

namespace GatePay\Core;

use Countable;
use GatePay\Core\Interfaces\GatewayInterface;
use function array_filter;
use function count;
use function get_class;
use function is_object;
use function is_string;
use function strrpos;
use function strtolower;
use function substr;

/**
 * This class serves as a registry for managing gateway instances in a payment processing system.
 * It allows for adding, retrieving, and removing gateway instances using their class names or aliases.
 * The registry maintains an associative array of registered gateways, where the keys are lowercase class names
 * and the values are the corresponding gateway instances. It also supports aliases for gateways,
 * allowing for more flexible retrieval of gateway instances.
 */
class GatewayRegistry implements Countable
{
    /**
     * @var array<class-string<GatewayInterface>&lowercase-string, GatewayInterface> $gateways
     * An associative array that holds the registered gateway instances,
     * where the keys are lowercase class names of the gateways
     * and the values are the corresponding gateway instances.
     */
    private array $gateways = [];

    /**
     * Aliases for the registered gateways, where the keys are the aliases (case-sensitive)
     * and the values are the original class names of the gateways.
     * @var array<string, class-string<GatewayInterface>> $aliasesName
     */
    private array $aliasesName = [];

    /**
     * An associative array that maps the original class names of the gateways
     * to their corresponding lowercase class name value in the $gateways array.
     * This allows for efficient retrieval of gateway instances using their original class names,
     * even when aliases are used.
     * @var array<class-string<GatewayInterface>, class-string<GatewayInterface>&lowercase-string>
     */
    private array $originalClassNames = [];

    /**
     * Get all registered gateways in the registry.
     *
     * @return array<class-string<GatewayInterface>&lowercase-string, GatewayInterface>
     */
    public function getGateways(): array
    {
        return $this->gateways;
    }

    /**
     * Get all registered aliases for the gateways in the registry.
     *
     * @return array<string, class-string<GatewayInterface>>
     */
    public function getAliases(): array
    {
        return $this->aliasesName;
    }

    /**
     * Adds an alias for a gateway adapter.
     *
     * @param string $alias The alias to be added for the gateway adapter.
     * @param string|GatewayInterface $adapter
     *      The gateway adapter instance or its class name for which the alias is being added.
     */
    public function addAlias(string $alias, string|GatewayInterface $adapter): ?GatewayInterface
    {
        $obj = $this->get($adapter);
        if ($obj === null) {
            return null; // Adapter not found, cannot add alias
        }
        $className = get_class($obj);
        $this->aliasesName[$alias] = $className;
        return $obj;
    }

    /**
     * Removes an alias for a gateway adapter.
     *
     * @param string $alias The alias to be removed for the gateway adapter.
     * its class name, or null to remove the alias from all adapters.
     */
    public function removeAlias(string $alias): void
    {
        unset($this->aliasesName[$alias]);
    }

    /**
     * Checks if a gateway with the specified name or instance exists in the registry.
     *
     * @param string|GatewayInterface|class-string<GatewayInterface> $name The name or instance of the gateway to check.
     * @return bool Returns true if the gateway exists, false otherwise.
     */
    public function has(string|GatewayInterface $name): bool
    {
        return $this->get($name) !== null;
    }

    /**
     * Add a gateway instance to the registry with an optional alias.
     * The method takes a gateway instance and an optional alias as parameters.
     *
     * @param GatewayInterface $gateway
     * @param string|null $alias
     */
    public function add(GatewayInterface $gateway, ?string $alias = null): void
    {
        $className = get_class($gateway);
        $lowerClassName = strtolower($className);
        if ($alias === null) {
            $alias = substr($className, strrpos($className, '\\') + 1);
        }
        /**
         * @var class-string<GatewayInterface>&lowercase-string $lowerClassName
         */
        $this->gateways[$lowerClassName] = $gateway;
        $this->aliasesName[$alias] = $className;
        $this->originalClassNames[$className] = $lowerClassName;
    }

    /**
     * Get a gateway instance by its name or instance.
     * The method first checks if the provided name is an object and retrieves its class name if it is.
     * Then, it looks for the gateway in the $gateways array using the lowercase class name as the key.
     * If not found, it checks the $aliasesName array for an
     * @template G of GatewayInterface
     * @param string|G|class-string<G> $name
     * @return ?G
     */
    public function get(string|GatewayInterface $name): ?GatewayInterface
    {
        /**
         * Object protection from binding to the class name,
         * and also support for getting gateway by instance.
         */
        if (is_object($name)) {
            $originalClassName = get_class($name);
            $name = strtolower($originalClassName);
        } elseif (isset($this->originalClassNames[$name])) { // based on class
            $name = $this->originalClassNames[$name];
        }
        if (isset($this->gateways[$name])) {
            if ($this->gateways[$name] instanceof GatewayInterface) {
                /**
                 * @var G $gw
                 */
                $gw = $this->gateways[$name];
                return $gw;
            }
            unset($this->gateways[$name]); // Remove the invalid gateway entry
            unset($this->originalClassNames[$name]); // Remove the original class name mapping
            unset($this->aliasesName[$name]); // Remove the alias if the gateway is not valid
            return null; // No gateway found for the given name
        }
        $className = $this->aliasesName[$name] ?? null;
        if (!is_string($className)) {
            return null; // No gateway found for the given name or alias
        }
        // get the lowercase class name from the original class name
        $lowerClassName = $this->originalClassNames[$className] ?? null;
        if (!is_string($lowerClassName)) {
            unset($this->aliasesName[$name]); // Remove the alias if the original class name is not found
            return null; // No gateway found for the given alias
        }
        if (!isset($this->gateways[$lowerClassName])) {
            unset($this->aliasesName[$name]); // Remove the alias if the gateway is not found
            unset($this->originalClassNames[$className]); // Remove the original class name mapping
            return null; // No gateway found for the given alias
        }
        if (!$this->gateways[$lowerClassName] instanceof GatewayInterface
            || strtolower(get_class($this->gateways[$lowerClassName])) !== $lowerClassName
        ) {
            unset($this->gateways[$lowerClassName]); // Remove the invalid gateway entry
            unset($this->aliasesName[$name]); // Remove the alias if the gateway is not valid
            unset($this->originalClassNames[$className]); // Remove the original class name mapping
            return null; // No valid gateway found for the given alias
        }
        /**
         * @var G $gw
         */
        $gw = $this->gateways[$lowerClassName];
        return $gw;
    }

    /**
     * Removes a gateway from the registry by its name or instance.
     * If the gateway is found and removed, it returns the removed gateway instance; otherwise, it returns null.
     * The method first attempts to retrieve the gateway using the provided name or instance.
     * If found, it removes the gateway from both the $gateways and $aliases arrays.
     *
     * @template G of GatewayInterface
     * @param string|G|class-string<G> $name
     * @return G|null Returns the removed gateway instance if found and removed, or null if not found.
     */
    public function remove(string|GatewayInterface $name): ?GatewayInterface
    {
        $gateway = $this->get($name);
        if ($gateway === null) {
            return null;
        }
        $className = get_class($gateway);
        $lowerClassName = strtolower($className);
        unset(
            $this->gateways[$lowerClassName],
            $this->originalClassNames[$className],
        );
        $this->aliasesName = array_filter(
            $this->aliasesName,
            fn($value) => $value !== $className
        );
        return $gateway;
    }

    /**
     * Returns the total number of registered gateways.
     *
     * @return int The count of registered gateways.
     */
    public function count(): int
    {
        return count($this->gateways);
    }
}
