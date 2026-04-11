<?php
declare(strict_types=1);

namespace GatePay\CoreTests\Utils;

use Closure;
use GatePay\Core\Utils\Utf8;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class Utf8Test extends TestCase
{
    private Utf8 $utf8;
    private function resetMbStringCache(?bool $value): void
    {
        Closure::bind(function (?bool $value) {
            self::$mbStringAvailable = $value;
        }, ($this->utf8 ??= new Utf8()), Utf8::class)($value);
    }

    protected function setUp(): void
    {
        $this->resetMbStringCache(null);
    }

    protected function tearDown(): void
    {
        $this->resetMbStringCache(null);
    }

    #[Test]
    public function isMbStringAvailableReturnsBool(): void
    {
        $this->assertIsBool(Utf8::isMbStringAvailable());
    }

    #[Test]
    public function isMbStringAvailableReturnsTrueWhenCacheSetToTrue(): void
    {
        $this->resetMbStringCache(true);

        $this->assertTrue(Utf8::isMbStringAvailable());
    }

    #[Test]
    public function isMbStringAvailableReturnsFalseWhenCacheSetToFalse(): void
    {
        $this->resetMbStringCache(false);

        $this->assertFalse(Utf8::isMbStringAvailable());
    }

    #[Test]
    public function replaceNullStringRemovesNullBytes(): void
    {
        $input = "Hello\x00World";

        $this->assertSame('HelloWorld', Utf8::replaceNullString($input));
    }

    #[Test]
    public function replaceNullStringRemovesControlCharacters(): void
    {
        $input = "Hello\x01\x02\x03World\x1F";

        $this->assertSame('HelloWorld', Utf8::replaceNullString($input));
    }

    #[Test]
    public function replaceNullStringPreservesTabNewlineAndCarriageReturn(): void
    {
        $input = "Hello\tWorld\nNew\rLine";

        $this->assertSame("Hello\tWorld\nNew\rLine", Utf8::replaceNullString($input));
    }

    #[Test]
    public function replaceNullStringRemovesBackslashZeroSequences(): void
    {
        $input = 'Hello\\0World\\00Test\\000End';

        $this->assertSame('HelloWorldTestEnd', Utf8::replaceNullString($input, true));
    }

    #[Test]
    public function replaceNullStringKeepsBackslashZeroWhenDisabled(): void
    {
        $input = 'Hello\\0World';

        $this->assertSame('Hello\\0World', Utf8::replaceNullString($input, false));
    }

    #[Test]
    public function replaceNullStringHandlesEmptyString(): void
    {
        $this->assertSame('', Utf8::replaceNullString(''));
    }

    #[Test]
    public function deepReplaceRemovesAllOccurrences(): void
    {
        $this->assertSame('HelloWorld', Utf8::deepReplace('x', 'HexlxloxWorld'));
    }

    #[Test]
    public function deepReplaceHandlesNestedPatterns(): void
    {
        $this->assertSame('Hello', Utf8::deepReplace('ab', 'Heabababllo'));
    }

    #[Test]
    public function deepReplaceHandlesArraySearch(): void
    {
        $this->assertSame('HelloWorld', Utf8::deepReplace(['x', 'y'], 'HxeylxloyWorld'));
    }

    #[Test]
    public function deepReplaceReturnsOriginalWhenNoMatch(): void
    {
        $this->assertSame('HelloWorld', Utf8::deepReplace('z', 'HelloWorld'));
    }

    #[Test]
    public function deepReplaceHandlesEmptyString(): void
    {
        $this->assertSame('', Utf8::deepReplace('x', ''));
    }

    #[Test]
    public function isUtf8ReturnsTrueForAsciiString(): void
    {
        $this->assertTrue(Utf8::isUtf8('Hello World'));
    }

    #[Test]
    public function isUtf8ReturnsTrueForValidUtf8(): void
    {
        $this->assertTrue(Utf8::isUtf8('Héllo Wörld'));
        $this->assertTrue(Utf8::isUtf8('日本語'));
        $this->assertTrue(Utf8::isUtf8('🎉'));
    }

    #[Test]
    public function isUtf8ReturnsTrueForEmptyString(): void
    {
        $this->assertTrue(Utf8::isUtf8(''));
    }

    #[Test]
    public function isUtf8ReturnsFalseForInvalidUtf8(): void
    {
        $this->assertFalse(Utf8::isUtf8("\xFF\xFE"));
        $this->assertFalse(Utf8::isUtf8("\x80\x81"));
    }

    #[Test]
    public function isUtf8WithMbStringReturnsTrueForValidUtf8(): void
    {
        $this->resetMbStringCache(true);

        $this->assertTrue(Utf8::isUtf8('Hello World'));
        $this->assertTrue(Utf8::isUtf8('日本語'));
    }

    #[Test]
    public function isUtf8WithoutMbStringReturnsTrueForValidUtf8(): void
    {
        $this->resetMbStringCache(false);

        $this->assertTrue(Utf8::isUtf8('Hello World'));
        $this->assertTrue(Utf8::isUtf8('Héllo'));
    }

    #[Test]
    public function isUtf8WithoutMbStringReturnsFalseForInvalidUtf8(): void
    {
        $this->resetMbStringCache(false);

        $this->assertFalse(Utf8::isUtf8("\xFF\xFE"));
        $this->assertFalse(Utf8::isUtf8("\xC0\x80"));
    }

    #[Test]
    public function isUtf8WithoutMbStringHandlesTwoByteSequence(): void
    {
        $this->resetMbStringCache(false);

        $this->assertTrue(Utf8::isUtf8("\xC2\xA9"));
        $this->assertFalse(Utf8::isUtf8("\xC2"));
    }

    #[Test]
    public function isUtf8WithoutMbStringHandlesThreeByteSequence(): void
    {
        $this->resetMbStringCache(false);

        $this->assertTrue(Utf8::isUtf8("\xE2\x82\xAC"));
        $this->assertFalse(Utf8::isUtf8("\xE2\x82"));
    }

    #[Test]
    public function isUtf8WithoutMbStringHandlesFourByteSequence(): void
    {
        $this->resetMbStringCache(false);

        $this->assertTrue(Utf8::isUtf8("\xF0\x9F\x8E\x89"));
        $this->assertFalse(Utf8::isUtf8("\xF0\x9F\x8E"));
    }

    #[Test]
    public function isUtf8WithoutMbStringRejectsOverlong2ByteSequence(): void
    {
        $this->resetMbStringCache(false);

        $this->assertFalse(Utf8::isUtf8("\xC0\xAF"));
        $this->assertFalse(Utf8::isUtf8("\xC1\xBF"));
    }

    #[Test]
    public function isUtf8WithoutMbStringRejectsOverlong3ByteSequence(): void
    {
        $this->resetMbStringCache(false);

        $this->assertFalse(Utf8::isUtf8("\xE0\x80\xAF"));
        $this->assertFalse(Utf8::isUtf8("\xE0\x9F\xBF"));
    }

    #[Test]
    public function isUtf8WithoutMbStringRejectsSurrogates(): void
    {
        $this->resetMbStringCache(false);

        $this->assertFalse(Utf8::isUtf8("\xED\xA0\x80"));
        $this->assertFalse(Utf8::isUtf8("\xED\xBF\xBF"));
    }

    #[Test]
    public function isUtf8WithoutMbStringRejectsOverlong4ByteSequence(): void
    {
        $this->resetMbStringCache(false);

        $this->assertFalse(Utf8::isUtf8("\xF0\x80\x80\xAF"));
    }

    #[Test]
    public function isUtf8WithoutMbStringRejectsOutOfRangeCodePoints(): void
    {
        $this->resetMbStringCache(false);

        $this->assertFalse(Utf8::isUtf8("\xF4\x90\x80\x80"));
    }

    #[Test]
    public function encodeReturnsValidUtf8Unchanged(): void
    {
        $input = 'Hello World';

        $this->assertSame('Hello World', Utf8::encode($input));
    }

    #[Test]
    public function encodeReturnsValidUtf8WithSpecialCharsUnchanged(): void
    {
        $input = '日本語テスト';

        $this->assertSame('日本語テスト', Utf8::encode($input));
    }

    #[Test]
    public function encodeWithMbStringReturnsValidUtf8(): void
    {
        $this->resetMbStringCache(true);
        $input = 'Hello World';

        $this->assertSame('Hello World', Utf8::encode($input));
    }

    #[Test]
    public function encodeWithoutMbStringReturnsValidUtf8(): void
    {
        $this->resetMbStringCache(false);
        $input = 'Hello World';

        $this->assertSame('Hello World', Utf8::encode($input));
    }

    #[Test]
    public function encodeHandlesEmptyString(): void
    {
        $this->assertSame('', Utf8::encode(''));
    }

    #[Test]
    public function encodeFallbackReturnsValidUtf8ForAscii(): void
    {
        $this->assertSame('Hello', Utf8::encodeFallback('Hello'));
    }

    #[Test]
    public function encodeFallbackReturnsValidUtf8ForValidInput(): void
    {
        $input = 'Test日本語';

        $this->assertTrue(Utf8::isUtf8(Utf8::encodeFallback($input)));
    }

    #[Test]
    public function encodeFallbackHandlesInvalidBytes(): void
    {
        $input = "Hello\xFFWorld";
        $result = Utf8::encodeFallback($input);

        $this->assertTrue(Utf8::isUtf8($result));
    }

    #[Test]
    public function encodeFallbackHandlesEmptyString(): void
    {
        $this->assertSame('', Utf8::encodeFallback(''));
    }

    #[Test]
    public function decodeReturnsValidUtf8Unchanged(): void
    {
        $input = 'Hello World';

        $this->assertSame('Hello World', Utf8::decode($input));
    }

    #[Test]
    public function decodeWithMbStringReturnsOriginalForValidUtf8(): void
    {
        $this->resetMbStringCache(true);
        $input = 'Hello World';

        $this->assertSame('Hello World', Utf8::decode($input));
    }

    #[Test]
    public function decodeWithoutMbStringHandlesAscii(): void
    {
        $this->resetMbStringCache(false);
        $input = 'Hello World';

        $this->assertSame('Hello World', Utf8::decode($input));
    }

    #[Test]
    public function decodeWithoutMbStringHandlesTwoByteCharacters(): void
    {
        $this->resetMbStringCache(false);
        $input = "\xC3\xA9";

        $result = Utf8::decode($input);
        $this->assertSame("\xE9", $result);
    }

    #[Test]
    public function decodeHandlesEmptyString(): void
    {
        $this->assertSame('', Utf8::decode(''));
    }

    #[Test]
    public function decodeWithoutMbStringReplacesThreeByteCharsWithQuestionMark(): void
    {
        $this->resetMbStringCache(false);
        $input = "\xE2\x82\xAC";

        $result = Utf8::decode($input);
        $this->assertSame('?', $result);
    }

    #[Test]
    public function decodeWithoutMbStringReplacesFourByteCharsWithQuestionMark(): void
    {
        $this->resetMbStringCache(false);
        $input = "\xF0\x9F\x8E\x89";

        $result = Utf8::decode($input);
        $this->assertSame('?', $result);
    }

    #[Test]
    public function encodeUsesFallbackWhenNoExtensionsAvailable(): void
    {
        $this->resetMbStringCache(false);
        $input = "Test\xFFData";

        $result = Utf8::encode($input);
        $this->assertTrue(Utf8::isUtf8($result));
    }

    #[Test]
    public function isUtf8WithoutMbStringRejectsIllegalByte(): void
    {
        $this->resetMbStringCache(false);

        $this->assertFalse(Utf8::isUtf8("\xFE"));
        $this->assertFalse(Utf8::isUtf8("\xFF"));
    }

    #[Test]
    public function isUtf8WithoutMbStringRejectsIncomplete2ByteSequence(): void
    {
        $this->resetMbStringCache(false);

        $this->assertFalse(Utf8::isUtf8("\xC2"));
        $this->assertFalse(Utf8::isUtf8("\xDF"));
    }

    #[Test]
    public function isUtf8WithoutMbStringRejectsInvalidContinuationByte(): void
    {
        $this->resetMbStringCache(false);

        $this->assertFalse(Utf8::isUtf8("\xC2\x00"));
        $this->assertFalse(Utf8::isUtf8("\xC2\xFF"));
    }

    #[Test]
    public function isUtf8WithoutMbStringRejectsInvalid3ByteSecondContinuationByte(): void
    {
        $this->resetMbStringCache(false);

        $this->assertFalse(Utf8::isUtf8("\xE2\x00\x80"));
        $this->assertFalse(Utf8::isUtf8("\xE2\xFF\x80"));
    }

    #[Test]
    public function isUtf8WithoutMbStringRejectsInvalid3ByteThirdContinuationByte(): void
    {
        $this->resetMbStringCache(false);

        $this->assertFalse(Utf8::isUtf8("\xE2\x82\x00"));
        $this->assertFalse(Utf8::isUtf8("\xE2\x82\xFF"));
    }

    #[Test]
    public function encodeWithAnotherMiscellaneousResult() : void
    {
        $input = "Hello\x80\x81\x82World";  // Invalid UTF-8 bytes
        $this->resetMbStringCache(false);
        $data = Utf8::encode($input);
        $this->assertNotSame(
            $input,
            $data
        );
        $this->assertTrue(Utf8::isUtf8($data));
        $result = Closure::bind(function (string $data) {
            // access private
            return $this->pureBinaryFallback($data);
        }, new Utf8(), Utf8::class)("y");
        $this->assertSame(
            "\x79",
            $result
        );
    }

    #[Test]
    public function isUtf8WithoutMbStringRejectsInvalid4ByteSecondContinuationByte(): void
    {
        $this->resetMbStringCache(false);

        $this->assertFalse(Utf8::isUtf8("\xF0\x00\x80\x80"));
        $this->assertFalse(Utf8::isUtf8("\xF0\xFF\x80\x80"));
    }

    #[Test]
    public function isUtf8WithoutMbStringRejectsInvalid4ByteThirdContinuationByte(): void
    {
        $this->resetMbStringCache(false);

        $this->assertFalse(Utf8::isUtf8("\xF0\x90\x00\x80"));
        $this->assertFalse(Utf8::isUtf8("\xF0\x90\xFF\x80"));
    }

    #[Test]
    public function isUtf8WithoutMbStringRejectsInvalid4ByteFourthContinuationByte(): void
    {
        $this->resetMbStringCache(false);

        $this->assertFalse(Utf8::isUtf8("\xF0\x90\x80\x00"));
        $this->assertFalse(Utf8::isUtf8("\xF0\x90\x80\xFF"));
    }

    #[Test]
    public function isUtf8WithoutMbStringRejectsFirstByteGreaterThanF4(): void
    {
        $this->resetMbStringCache(false);

        $this->assertFalse(Utf8::isUtf8("\xF5\x80\x80\x80"));
        $this->assertFalse(Utf8::isUtf8("\xF6\x80\x80\x80"));
        $this->assertFalse(Utf8::isUtf8("\xF7\x80\x80\x80"));
    }
}
