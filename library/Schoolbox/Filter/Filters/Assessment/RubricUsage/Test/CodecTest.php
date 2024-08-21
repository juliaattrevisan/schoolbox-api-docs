<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\RubricUsage\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Model as CapabilityModel;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Model as IndicatorModel;
use Schoolbox\Filter\Filters\Assessment\RubricUsage\Codec;
use UnexpectedValueException;

/**
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Filter\Filters\Assessment\RubricUsage\Codec::class)]
class CodecTest extends TestCase
{

    /** @var Codec */
    private static $codec;


    /**
     * This method is called before the first test of this test class is run.
     */
    public static function setUpBeforeClass(): void
    {
        self::$codec = new Codec();
    }

    // ==================== Tests ====================
    /**
     * @param CapabilityModel $capability
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerEncodeInvalidCapability')]
    public function testEncodeInvalidCapability(CapabilityModel $capability): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::$codec->encodeCapability($capability);
    }


    /**
     * @param CapabilityModel $capability
     * @param string          $expected
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerEncodeCapability')]
    public function testEncodeCapability(CapabilityModel $capability, string $expected): void
    {
        $this->assertSame($expected, self::$codec->encodeCapability($capability));
    }


    /**
     * @param IndicatorModel $indicator
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerEncodeInvalidIndicator')]
    public function testEncodeInvalidIndicator(IndicatorModel $indicator): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::$codec->encodeIndicator($indicator);
    }


    /**
     * @param IndicatorModel $indicator
     * @param string         $expected
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerEncodeIndicator')]
    public function testEncodeIndicator(IndicatorModel $indicator, string $expected): void
    {
        $this->assertSame($expected, self::$codec->encodeIndicator($indicator));
    }


    /**
     * @param string $key
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidDecode')]
    public function testInvalidDecode(string $key): void
    {
        $this->expectException(UnexpectedValueException::class);
        self::$codec->decode($key);
    }


    /**
     * @param string $key
     * @param int    $expectedCapability
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCapabilityDecode')]
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
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIndicatorDecode')]
    public function testIndicatorDecode(string $key, int $expectedCapability, int $expectedIndicator): void
    {
        [$capabilityId, $indicatorId] = self::$codec->decode($key);
        $this->assertSame($expectedCapability, $capabilityId);
        $this->assertSame($expectedIndicator, $indicatorId);
    }


    // ==================== Providers ====================


    /** @return array */
    public static function providerEncodeInvalidCapability(): array
    {
        $data = [];
        foreach (self::invalidCapabilities() as $capability) {
            $data[] = [$capability];
        }
        return $data;
    }


    /** @return array */
    public static function providerEncodeCapability(): array
    {
        return [
            [(new CapabilityModel())->setId(1), 'c1',],
            [(new CapabilityModel())->setId(100), 'c100',],
            [(new CapabilityModel())->setId('999'), 'c999',],
        ];
    }


    /** @return array */
    public static function providerEncodeInvalidIndicator(): array
    {
        $data = [];

        // Valid Cap + Invalid Ind
        foreach (self::validCapabilities() as $validCapability) {
            $capability = clone $validCapability;
            foreach (self::invalidIndicators() as $invalidIndicator) {
                $indicator = clone $invalidIndicator;
                $indicator->setCapability($capability);
                $data[] = [$indicator];
            }
        }

        // Invalid Cap + Valid Ind
        foreach (self::invalidCapabilities() as $invalidCapability) {
            $capability = clone $invalidCapability;
            foreach (self::validIndicators() as $validIndicator) {
                $indicator = clone $validIndicator;
                $indicator->setCapability($capability);
                $data[] = [$indicator];
            }
        }
        return $data;
    }


    /** @return array */
    public static function providerEncodeIndicator(): array
    {
        return [
            [(new IndicatorModel())->setCapability((new CapabilityModel())->setId(1))->setId(1), 'c1i1',],
            [(new IndicatorModel())->setCapability((new CapabilityModel())->setId(1))->setId(100), 'c1i100',],
            [(new IndicatorModel())->setCapability((new CapabilityModel())->setId(1))->setId(999), 'c1i999',],
            [(new IndicatorModel())->setCapability((new CapabilityModel())->setId(100))->setId(100), 'c100i100',],
            [(new IndicatorModel())->setCapability((new CapabilityModel())->setId(999))->setId(999), 'c999i999',],
        ];
    }


    /** @return array */
    public static function providerInvalidDecode(): array
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
    public static function providerCapabilityDecode(): array
    {
        return [
            ['c1', 1],
            ['c100', 100],
            ['c999', 999],
        ];
    }


    /** @return array */
    public static function providerIndicatorDecode(): array
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
     * @return CapabilityModel[]
     */
    private static function invalidCapabilities(): array
    {
        return [
            new CapabilityModel(),
            (new CapabilityModel())->setId(null),
        ];
    }


    /**
     * @return CapabilityModel[]
     */
    private static function validCapabilities(): array
    {
        return [
            (new CapabilityModel())->setId(1),
            (new CapabilityModel())->setId(100),
            (new CapabilityModel())->setId('999'),
        ];
    }


    /**
     * @return IndicatorModel[]
     */
    private static function invalidIndicators(): array
    {
        return [
            new IndicatorModel(),
            (new IndicatorModel())->setId(null),
        ];
    }


    /**
     * @return IndicatorModel[]
     */
    private static function validIndicators(): array
    {
        return [
            (new IndicatorModel())->setId(1),
            (new IndicatorModel())->setId(100),
            (new IndicatorModel())->setId('999'),
        ];
    }
}
