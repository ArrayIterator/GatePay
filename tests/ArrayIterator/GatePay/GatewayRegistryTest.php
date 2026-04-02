<?php
declare(strict_types=1);

namespace ArrayIterator\GatePay;

use PHPUnit\Framework\TestCase;

// TODO: Implement tests for the GatewayRegistry class, covering all methods and edge cases.
class GatewayRegistryTest extends TestCase
{
    private GatewayRegistry $registry;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->registry = new GatewayRegistry();
    }

    /**
     * @test
     */
    public function testHas()  : void
    {
        $this->assertFalse(
            $this->registry->has("inexistentGateway")
        );
    }

    public function testAdd()  : void
    {
    }

    public function testGet()  : void
    {
    }

    public function testRemove()  : void
    {
    }

    public function testGetAliases()  : void
    {
    }

    public function testRemoveAlias()  : void
    {
    }

    public function testCount()  : void
    {
    }

    public function testGetGateways()  : void
    {
    }

    public function testAddAlias()  : void
    {
    }
}
