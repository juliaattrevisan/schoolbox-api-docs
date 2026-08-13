<?php

namespace Evaluation\Answer\Test;

use Evaluation\Answer\SafeUnserialize;
use PHPUnit\Framework\TestCase;

class SafeUnserializeTest extends TestCase
{
    private function subject()
    {
        return new class {
            use SafeUnserialize;

            public function call($value)
            {
                return $this->safeUnserialize($value);
            }
        };
    }

    public function testNullReturnsFalse()
    {
        $this->assertFalse($this->subject()->call(null));
    }

    public function testEmptyStringReturnsFalse()
    {
        $this->assertFalse($this->subject()->call(''));
    }

    /**
     * The write-side bug (SA-20845) stored this literal string instead of a
     * real SQL NULL; historical rows still contain it.
     */
    public function testLiteralNullStringReturnsFalse()
    {
        $this->assertFalse($this->subject()->call('NULL'));
    }

    public function testNonStringReturnsFalse()
    {
        $this->assertFalse($this->subject()->call(['not', 'a', 'string']));
    }

    public function testValidSerializedArrayIsUnserialized()
    {
        $this->assertSame(['a', 'b'], $this->subject()->call(serialize(['a', 'b'])));
    }

    /**
     * serialize(false) is the 4-byte string 'b:0;', which must not be
     * confused with "invalid data" despite unserialize() also returning
     * false for genuinely invalid input.
     */
    public function testSerializedFalseRoundTrips()
    {
        $this->assertFalse($this->subject()->call(serialize(false)));
        $this->assertSame(false, $this->subject()->call(serialize(false)));
    }

    public function testValidSerializedScalarIsUnserialized()
    {
        $this->assertSame(42, $this->subject()->call(serialize(42)));
    }
}
