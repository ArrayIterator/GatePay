<?php
declare(strict_types=1);

namespace GatePay\CoreTests;

use Closure;
use GatePay\Core\GatewayRegistry;
use GatePay\Example\DummyGateway\DummyGateway;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GatewayRegistryTest extends TestCase
{
    private GatewayRegistry $registry;

    private DummyGateway $dummyGateway;

    protected function setUp(): void
    {
        $this->registry = new GatewayRegistry();
        $this->dummyGateway = new DummyGateway(new HttpFactory());

        foreach ($this->registry->getGateways() as $gateway) {
            $this->registry->remove($gateway::class);
        }
    }

    #[Test]
    public function addGatewayWithAliasIncreasesCount(): void
    {
        $this->registry->add($this->dummyGateway, 'Dummy');

        $this->assertSame(1, count($this->registry));
        $this->assertSame(1, $this->registry->count());
    }

    #[Test]
    public function addGatewayWithoutAliasUsesBaseClassName(): void
    {
        $this->registry->add($this->dummyGateway);

        $this->assertTrue($this->registry->has(DummyGateway::class));
        $this->assertTrue($this->registry->has('DummyGateway'));
    }

    #[Test]
    public function hasReturnsTrueForExistingGateway(): void
    {
        $this->registry->add($this->dummyGateway, 'Dummy');

        $this->assertTrue($this->registry->has(DummyGateway::class));
        $this->assertTrue($this->registry->has('Dummy'));
    }

    #[Test]
    public function hasReturnsFalseForNonExistentGateway(): void
    {
        $this->assertFalse($this->registry->has('NonExistent'));
        $this->assertFalse($this->registry->has(DummyGateway::class));
    }

    #[Test]
    public function addAliasCreatesNewAliasForExistingGateway(): void
    {
        $this->registry->add($this->dummyGateway, 'Dummy');

        $result = $this->registry->addAlias('DummyAlias', DummyGateway::class);

        $this->assertSame($this->dummyGateway, $result);
        $this->assertTrue($this->registry->has('DummyAlias'));
    }

    #[Test]
    public function addAliasReturnsNullForNonExistentGateway(): void
    {
        $result = $this->registry->addAlias('SomeAlias', DummyGateway::class);

        $this->assertNull($result);
    }

    #[Test]
    public function getByClassNameReturnsGatewayInstance(): void
    {
        $this->registry->add($this->dummyGateway, 'Dummy');

        $this->assertSame($this->dummyGateway, $this->registry->get(DummyGateway::class));
    }

    #[Test]
    public function getByInstanceReturnsGatewayInstance(): void
    {
        $this->registry->add($this->dummyGateway, 'Dummy');

        $this->assertSame($this->dummyGateway, $this->registry->get($this->dummyGateway));
    }

    #[Test]
    public function getByAliasReturnsGatewayInstance(): void
    {
        $this->registry->add($this->dummyGateway, 'Dummy');

        $this->assertSame($this->dummyGateway, $this->registry->get('Dummy'));
    }

    #[Test]
    public function getReturnsNullForNonExistentGateway(): void
    {
        $this->assertNull($this->registry->get('NonExistent'));
    }

    #[Test]
    public function getAliasesReturnsAllRegisteredAliases(): void
    {
        $this->registry->add($this->dummyGateway, 'Dummy');
        $this->registry->add($this->dummyGateway);
        $this->registry->addAlias('DummyAlias', DummyGateway::class);

        $aliases = array_keys($this->registry->getAliases());

        $this->assertContains('Dummy', $aliases);
        $this->assertContains('DummyGateway', $aliases);
        $this->assertContains('DummyAlias', $aliases);
    }

    #[Test]
    public function removeAliasRemovesOnlyTheAlias(): void
    {
        $this->registry->add($this->dummyGateway, 'Dummy');
        $this->registry->addAlias('DummyAlias', DummyGateway::class);

        $this->registry->removeAlias('DummyAlias');

        $this->assertFalse($this->registry->has('DummyAlias'));
        $this->assertTrue($this->registry->has('Dummy'));
        $this->assertTrue($this->registry->has(DummyGateway::class));
    }

    #[Test]
    public function removeGatewayReturnsRemovedInstance(): void
    {
        $this->registry->add($this->dummyGateway, 'Dummy');

        $removed = $this->registry->remove(DummyGateway::class);

        $this->assertSame($this->dummyGateway, $removed);
    }

    #[Test]
    public function removeGatewayReturnsNullForNonExistent(): void
    {
        $this->assertNull($this->registry->remove(DummyGateway::class));
    }

    #[Test]
    public function removeGatewayAlsoRemovesAllAliases(): void
    {
        $this->registry->add($this->dummyGateway, 'Dummy');
        $this->registry->addAlias('DummyAlias', DummyGateway::class);

        $this->registry->remove(DummyGateway::class);

        $this->assertFalse($this->registry->has(DummyGateway::class));
        $this->assertFalse($this->registry->has('Dummy'));
        $this->assertFalse($this->registry->has('DummyAlias'));
    }

    #[Test]
    public function getReturnsNullWhenAliasExistsButGatewayDoesNot(): void
    {
        Closure::bind(function () {
            $this->aliasesName['Dummy'] = DummyGateway::class;
        }, $this->registry, GatewayRegistry::class)();

        $this->assertNull($this->registry->get('Dummy'));
    }

    #[Test]
    public function getReturnsNullWhenGatewayValueIsNotInstance(): void
    {
        Closure::bind(function () {
            $this->aliasesName['Dummy'] = DummyGateway::class;
            $this->gateways[strtolower(DummyGateway::class)] = DummyGateway::class;
            $this->originalClassNames[DummyGateway::class] = strtolower(DummyGateway::class);
        }, $this->registry, GatewayRegistry::class)();

        $this->assertNull($this->registry->get(DummyGateway::class));
    }

    #[Test]
    public function getReturnsNullWhenOriginalClassNameMismatch(): void
    {
        Closure::bind(function () {
            $this->aliasesName['Dummy'] = DummyGateway::class;
            $this->originalClassNames[DummyGateway::class] = 'not_a_gateway';
        }, $this->registry, GatewayRegistry::class)();

        $this->assertNull($this->registry->get('Dummy'));
    }

    #[Test]
    public function getReturnsNullWhenGatewayStoredAsInvalidValue(): void
    {
        Closure::bind(function () {
            $this->aliasesName['Dummy'] = DummyGateway::class;
            $this->originalClassNames[DummyGateway::class] = strtolower(DummyGateway::class);
            $this->gateways[strtolower(DummyGateway::class)] = 'not_a_gateway';
        }, $this->registry, GatewayRegistry::class)();

        $this->assertNull($this->registry->get('Dummy'));
    }
}
