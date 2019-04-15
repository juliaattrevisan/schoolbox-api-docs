<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\CapabilityIndicator\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Criterion;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Group;
use Schoolbox\Filter\Filters\Assessment\CapabilityIndicator\Codec;
use UnexpectedValueException;


/**
 * @covers \Schoolbox\Filter\Filters\Assessment\CapabilityIndicator\Codec
 * @codeCoverageIgnore
 */
class CodecTest extends TestCase
{

    /** @var Codec */
    private static $codec;


    /**
     * This method is called before the first test of this test class is run.
     */
    public static function setUpBeforeClass()
    {
        self::$codec = new Codec();
    }

    // ==================== Tests ====================


    /**
     * @param Group $capability
     * @dataProvider providerEncodeInvalidCapability
     * @expectedException InvalidArgumentException
     */
    public function testEncodeInvalidCapability(Group $capability): void
    {
        self::$codec->encodeCapability($capability);
    }


    /**
     * @param Group  $capability
     * @param string $expected
     * @dataProvider providerEncodeCapability
     * @throws InvalidArgumentException
     */
    public function testEncodeCapability(Group $capability, string $expected): void
    {
        $this->assertSame($expected, self::$codec->encodeCapability($capability));
    }


    /**
     * @param Criterion $indicator
     * @dataProvider providerEncodeInvalidIndicator
     * @expectedException InvalidArgumentException
     */
    public function testEncodeInvalidIndicator(Criterion $indicator): void
    {
        self::$codec->encodeIndicator($indicator);
    }


    /**
     * @param Criterion $indicator
     * @param string    $expected
     * @dataProvider providerEncodeIndicator
     * @throws InvalidArgumentException
     */
    public function testEncodeIndicator(Criterion $indicator, string $expected): void
    {
        $this->assertSame($expected, self::$codec->encodeIndicator($indicator));
    }


    /**
     * @param string $key
     * @dataProvider providerInvalidDecode
     * @expectedException UnexpectedValueException
     */
    public function testInvalidDecode(string $key): void
    {
        self::$codec->decode($key);
    }


    /**
     * @param string $key
     * @param int    $expectedCapability
     * @dataProvider providerCapabilityDecode
     * @throws UnexpectedValueException
     */
    public function testCapabilityDecode(string $key, int $expectedCapability): void
    {
        [$capabilityId, $indicatorId] = self::$codec->decode($key);
        $this->assertSame($expectedCapability, $capabilityId);
        $this->assertNull($indicatorId);
    }


    /**
     * @param string $key
     * @param int    $expectedCapability
     * @param int    $expectedIndicator
     * @dataProvider providerIndicatorDecode
     * @throws UnexpectedValueException
     */
    public function testIndicatorDecode(string $key, int $expectedCapability, int $expectedIndicator): void
    {
        [$capabilityId, $indicatorId] = self::$codec->decode($key);
        $this->assertSame($expectedCapability, $capabilityId);
        $this->assertSame($expectedIndicator, $indicatorId);
    }


    // ==================== Providers ====================


    /** @return array */
    public function providerEncodeInvalidCapability(): array
    {
        $data = [];
        foreach ($this->invalidCapabilities() as $capability) {
            $data[] = [$capability];
        }
        return $data;
    }


    /** @return array */
    public function providerEncodeCapability(): array
    {
        return [
            [(new Group())->setId(1), 'c1',],
            [(new Group())->setId(100), 'c100',],
            [(new Group())->setId('999'), 'c999',],
        ];
    }


    /** @return array */
    public function providerEncodeInvalidIndicator(): array
    {
        $data = [];

        // Valid Cap + Invalid Ind
        foreach ($this->validCapabilities() as $validCapability) {
            $capability = clone $validCapability;
            foreach ($this->invalidIndicators() as $invalidIndicator) {
                $indicator = clone $invalidIndicator;
                $indicator->setGroup($capability);
                $data[] = [$indicator];
            }
        }

        // Invalid Cap + Valid Ind
        foreach ($this->invalidCapabilities() as $invalidCapability) {
            $capability = clone $invalidCapability;
            foreach ($this->validIndicators() as $validIndicator) {
                $indicator = clone $validIndicator;
                $indicator->setGroup($capability);
                $data[] = [$indicator];
            }
        }
        return $data;
    }


    /** @return array */
    public function providerEncodeIndicator(): array
    {
        return [
            [(new Criterion())->setGroup((new Group())->setId(1))->setId(1), 'c1i1',],
            [(new Criterion())->setGroup((new Group())->setId(1))->setId(100), 'c1i100',],
            [(new Criterion())->setGroup((new Group())->setId(1))->setId(999), 'c1i999',],
            [(new Criterion())->setGroup((new Group())->setId(100))->setId(100), 'c100i100',],
            [(new Criterion())->setGroup((new Group())->setId(999))->setId(999), 'c999i999',],
        ];
    }


    /** @return array */
    public function providerInvalidDecode(): array
    {
        return [
            [''],
            ['c'],
            ['i'],
            ['ci'],
            ['c0'],
            ['i0'],
            ['c0i'],
            ['c1i'],
            ['c1i0'],
            ['ci1'],
            ['c0i1'],
            ['x1'],
            ['x1i1'],
            ['c1x1'],
            ['i1c1'],
            ['c1i1x'],
        ];
    }


    /** @return array */
    public function providerCapabilityDecode(): array
    {
        return [
            ['c1', 1],
            ['c100', 100],
            ['c999', 999],
        ];
    }


    /** @return array */
    public function providerIndicatorDecode(): array
    {
        return [
            ['c1i1', 1, 1,],
            ['c1i100', 1, 100,],
            ['c1i999', 1, 999,],
            ['c100i100', 100, 100,],
            ['c999i999', 999, 999,],
        ];
    }


    // ==================== Helpers ====================


    /**
     * @return Group[]
     */
    private function invalidCapabilities(): array
    {
        return [
            new Group(),
            (new Group())->setId(null),
        ];
    }


    /**
     * @return Group[]
     */
    private function validCapabilities(): array
    {
        return [
            (new Group())->setId(1),
            (new Group())->setId(100),
            (new Group())->setId('999'),
        ];
    }


    /**
     * @return Criterion[]
     */
    private function invalidIndicators(): array
    {
        return [
            new Criterion(),
            (new Criterion())->setId(null),
        ];
    }


    /**
     * @return Criterion[]
     */
    private function validIndicators(): array
    {
        return [
            (new Criterion())->setId(1),
            (new Criterion())->setId(100),
            (new Criterion())->setId('999'),
        ];
    }
}
