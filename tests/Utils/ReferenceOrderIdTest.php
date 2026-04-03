<?php
declare(strict_types=1);

namespace GatePay\CoreTests\Utils;

use GatePay\Core\Utils\ReferenceOrderId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReferenceOrderIdTest extends TestCase
{
    #[Test]
    public function constructorUsesDefaultPrefixWhenNotProvided(): void
    {
        $generator = new ReferenceOrderId();

        $this->assertSame('ORDR', $generator->getProductPrefix());
    }

    #[Test]
    public function constructorFormatsCustomPrefix(): void
    {
        $generator = new ReferenceOrderId('test');

        $this->assertSame('TEST', $generator->getProductPrefix());
    }

    #[Test]
    public function formatPrefixTrimsAndUppercasesInput(): void
    {
        $this->assertSame('TEST', ReferenceOrderId::formatPrefix('  test  '));
    }

    #[Test]
    public function formatPrefixRemovesNonAlphanumericCharacters(): void
    {
        $this->assertSame('AB12', ReferenceOrderId::formatPrefix('a@b#1$2'));
    }

    #[Test]
    public function formatPrefixPadsShortInputWithX(): void
    {
        $this->assertSame('ABXX', ReferenceOrderId::formatPrefix('ab'));
        $this->assertSame('AXXX', ReferenceOrderId::formatPrefix('a'));
        $this->assertSame('XXXX', ReferenceOrderId::formatPrefix(''));
    }

    #[Test]
    public function formatPrefixTruncatesLongInput(): void
    {
        $this->assertSame('ABCD', ReferenceOrderId::formatPrefix('abcdefgh'));
    }

    #[Test]
    public function setProductPrefixUpdatesPrefix(): void
    {
        $generator = new ReferenceOrderId();
        $generator->setProductPrefix('new1');

        $this->assertSame('NEW1', $generator->getProductPrefix());
    }

    #[Test]
    public function generateReturnsStringOf30Characters(): void
    {
        $generator = new ReferenceOrderId();
        $orderId = $generator->generate();

        $this->assertSame(30, strlen($orderId));
    }

    #[Test]
    public function generateReturnsValidOrderIdFormat(): void
    {
        $generator = new ReferenceOrderId();
        $orderId = $generator->generate();

        $this->assertTrue(ReferenceOrderId::isValid($orderId));
    }

    #[Test]
    public function generateIncludesProductPrefixInOrderId(): void
    {
        $generator = new ReferenceOrderId('HSTG');
        $orderId = $generator->generate();

        $this->assertStringStartsWith('HSTG-', $orderId);
    }

    #[Test]
    public function generateProducesUniqueOrderIds(): void
    {
        $generator = new ReferenceOrderId();
        $orderIds = [];

        for ($i = 0; $i < 100; $i++) {
            $orderIds[] = $generator->generate();
        }

        $this->assertCount(100, array_unique($orderIds));
    }

    #[Test]
    public function isValidReturnsTrueForValidOrderId(): void
    {
        $this->assertTrue(ReferenceOrderId::isValid('ORDR-019d43d20eb8-6a5a7925dfb5'));
        $this->assertTrue(ReferenceOrderId::isValid('HSTG-000000000000-000000000000'));
        $this->assertTrue(ReferenceOrderId::isValid('AB12-abcdef123456-fedcba654321'));
    }

    #[Test]
    public function isValidReturnsFalseForInvalidPrefix(): void
    {
        $this->assertFalse(ReferenceOrderId::isValid('ORD-019d43d20eb8-6a5a7925dfb5'));
        $this->assertFalse(ReferenceOrderId::isValid('ORDRS-019d43d20eb8-6a5a7925dfb5'));
        $this->assertFalse(ReferenceOrderId::isValid('ordr-019d43d20eb8-6a5a7925dfb5'));
    }

    #[Test]
    public function isValidReturnsFalseForInvalidTimestamp(): void
    {
        $this->assertFalse(ReferenceOrderId::isValid('ORDR-019d43d20eb-6a5a7925dfb5'));
        $this->assertFalse(ReferenceOrderId::isValid('ORDR-019d43d20eb89-6a5a7925dfb5'));
        $this->assertFalse(ReferenceOrderId::isValid('ORDR-019d43d20ebg-6a5a7925dfb5'));
    }

    #[Test]
    public function isValidReturnsFalseForInvalidRandomPart(): void
    {
        $this->assertFalse(ReferenceOrderId::isValid('ORDR-019d43d20eb8-6a5a7925dfb'));
        $this->assertFalse(ReferenceOrderId::isValid('ORDR-019d43d20eb8-6a5a7925dfb56'));
        $this->assertFalse(ReferenceOrderId::isValid('ORDR-019d43d20eb8-6a5a7925dfbZ'));
    }

    #[Test]
    public function isValidReturnsFalseForMissingSeparators(): void
    {
        $this->assertFalse(ReferenceOrderId::isValid('ORDR019d43d20eb86a5a7925dfb5'));
        $this->assertFalse(ReferenceOrderId::isValid('ORDR-019d43d20eb86a5a7925dfb5'));
    }

    #[Test]
    public function isValidReturnsFalseForEmptyString(): void
    {
        $this->assertFalse(ReferenceOrderId::isValid(''));
    }

    #[Test]
    public function parseReturnsCorrectComponentsForValidOrderId(): void
    {
        $orderId = 'ORDR-019d43d20eb8-6a5a7925dfb5';
        $result = ReferenceOrderId::parse($orderId);

        $this->assertIsArray($result);
        $this->assertSame('ORDR', $result['prefix']);
        $this->assertSame('6a5a7925dfb5', $result['random']);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('timestamp_ms', $result);
        $this->assertArrayHasKey('created_at', $result);
    }

    #[Test]
    public function parseReturnsCorrectTimestamp(): void
    {
        $orderId = 'ORDR-019d43d20eb8-6a5a7925dfb5';
        $result = ReferenceOrderId::parse($orderId);

        $expectedTimestamp = hexdec('019d43d20eb8');
        $this->assertSame($expectedTimestamp, $result['timestamp']);
        $this->assertSame($expectedTimestamp, $result['timestamp_ms']);
    }

    #[Test]
    public function parseReturnsNullForInvalidOrderId(): void
    {
        $this->assertNull(ReferenceOrderId::parse('invalid-order-id'));
        $this->assertNull(ReferenceOrderId::parse(''));
        $this->assertNull(ReferenceOrderId::parse('ORDR-invalid'));
    }

    #[Test]
    public function getTimestampReturnsTimestampInMilliseconds(): void
    {
        $orderId = 'ORDR-019d43d20eb8-6a5a7925dfb5';
        $timestamp = ReferenceOrderId::getTimestamp($orderId);

        $this->assertSame((int)hexdec('019d43d20eb8'), $timestamp);
    }

    #[Test]
    public function getTimestampReturnsNullForInvalidOrderId(): void
    {
        $this->assertNull(ReferenceOrderId::getTimestamp('invalid'));
        $this->assertNull(ReferenceOrderId::getTimestamp(''));
    }

    #[Test]
    public function getTimeRangePatternReturnsCorrectFormat(): void
    {
        $fromMs = 1735123456000;
        $toMs = 1735209856000;

        $result = ReferenceOrderId::getTimeRangePattern('ORDR', $fromMs, $toMs);

        $this->assertArrayHasKey('from', $result);
        $this->assertArrayHasKey('to', $result);
        $this->assertArrayHasKey('pattern', $result);
        $this->assertSame('ORDR-%', $result['pattern']);
    }

    #[Test]
    public function getTimeRangePatternFormatsPrefix(): void
    {
        $result = ReferenceOrderId::getTimeRangePattern('test', 1000, 2000);

        $this->assertStringStartsWith('TEST-', $result['from']);
        $this->assertStringStartsWith('TEST-', $result['to']);
        $this->assertSame('TEST-%', $result['pattern']);
    }

    #[Test]
    public function getTimeRangePatternGeneratesCorrectTimestampHex(): void
    {
        $fromMs = 1735123456000;
        $toMs = 1735209856000;

        $result = ReferenceOrderId::getTimeRangePattern('ORDR', $fromMs, $toMs);

        $expectedFromHex = str_pad(dechex($fromMs & 0xFFFFFFFFFFFF), 12, '0', STR_PAD_LEFT);
        $expectedToHex = str_pad(dechex($toMs & 0xFFFFFFFFFFFF), 12, '0', STR_PAD_LEFT);

        $this->assertSame("ORDR-$expectedFromHex", $result['from']);
        $this->assertSame("ORDR-$expectedToHex", $result['to']);
    }

    #[Test]
    public function createRandomReturnsStringOfRequestedLength(): void
    {
        $generator = new ReferenceOrderId();

        $this->assertSame(10, strlen($generator->createRandom(10)));
        $this->assertSame(1, strlen($generator->createRandom(1)));
        $this->assertSame(32, strlen($generator->createRandom(32)));
    }

    #[Test]
    public function createRandomClampsLengthToMinimum(): void
    {
        $generator = new ReferenceOrderId();

        $this->assertSame(1, strlen($generator->createRandom(0)));
        $this->assertSame(1, strlen($generator->createRandom(-5)));
    }

    #[Test]
    public function createRandomClampsLengthToMaximum(): void
    {
        $generator = new ReferenceOrderId();

        $this->assertSame(32, strlen($generator->createRandom(100)));
        $this->assertSame(32, strlen($generator->createRandom(50)));
    }

    #[Test]
    public function generatedOrderIdCanBeParsedCorrectly(): void
    {
        $generator = new ReferenceOrderId('PROD');
        $orderId = $generator->generate();

        $parsed = ReferenceOrderId::parse($orderId);

        $this->assertNotNull($parsed);
        $this->assertSame('PROD', $parsed['prefix']);
        $this->assertSame(12, strlen($parsed['random']));
    }

    #[Test]
    public function generatedTimestampIsApproximatelyCurrentTime(): void
    {
        $beforeMs = (int)(microtime(true) * 1000);
        $generator = new ReferenceOrderId();
        $orderId = $generator->generate();
        $afterMs = (int)(microtime(true) * 1000);

        $timestamp = ReferenceOrderId::getTimestamp($orderId);

        $this->assertGreaterThanOrEqual($beforeMs, $timestamp);
        $this->assertLessThanOrEqual($afterMs, $timestamp);
    }
}
