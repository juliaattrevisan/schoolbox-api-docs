<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Web\Request\Test;

use Alaress\Web\Request\Values;
use PHPUnit\Framework\TestCase;

/**
 * Port of tests for `\Aura\Web\Request\Values`.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Alaress\Web\Request\Values::class)]
class ValuesTest extends TestCase
{
    /**
     * Test values for the request values object.
     */
    private const TEST_VALUES = [
        'foo'     => 'bar',
        'true'    => true,
        'false'   => false,
        '1'       => 1,
        '11'      => 11,
        '-11'     => -11,
        '0'       => 0,
        '0.5'     => 0.5,
        'truthy'  => 'y',
        'falsy'   => 'off',
        'neither' => '???',
    ];

    /**
     *
     * @param Values $values
     * @param string $key
     * @param mixed  $alt
     * @param mixed  $expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetData')]
    public function testGet(Values $values, string $key, mixed $alt, mixed $expected): void
    {
        $this->assertSame($expected, $values->get($key, $alt));
    }

    /**
     * Data provider for `testGet`.
     *
     * @return iterable
     */
    public static function getTestGetData(): iterable
    {
        $values = new Values(self::TEST_VALUES);
        yield 'key exists'              => [$values, 'foo', null, 'bar'];
        yield 'key exists: alt'         => [$values, 'foo', 'goop', 'bar'];
        yield 'key does not exist'      => [$values, 'baz', null, null];
        yield 'key does not exist: alt' => [$values, 'baz', 'goop', 'goop'];
    }

    /**
     *
     * @param Values    $values
     * @param string    $key
     * @param bool|null $alt
     * @param bool|null $expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetBoolData')]
    public function testGetBool(Values $values, string $key, ?bool $alt, ?bool $expected): void
    {
        $this->assertSame($expected, $values->getBool($key, $alt));
    }

    /**
     * Data provider for `testGetBool`.
     *
     * @return iterable
     */
    public static function getTestGetBoolData(): iterable
    {
        $values = new Values(self::TEST_VALUES);
        yield 'true'                    => [$values, 'true', null, true];
        yield 'false'                   => [$values, 'false', null, false];
        yield 'numeric 1'               => [$values, '1', null, true];
        yield 'numeric 11'              => [$values, '11', null, true];
        yield 'numeric -11'             => [$values, '-11', null, true];
        yield 'numeric 0'               => [$values, '0', null, false];
        yield 'numeric 0.5'             => [$values, '0.5', null, true];
        yield 'truthy'                  => [$values, 'truthy', null, true];
        yield 'falsy'                   => [$values, 'falsy', null, false];
        yield 'neither'                 => [$values, 'neither', null, null];
        yield 'key does not exist'      => [$values, 'null', null, null];
        yield 'key does not exist: alt' => [$values, 'null', true, true];
    }

    /**
     *
     * @param Values   $values
     * @param string   $key
     * @param int|null $alt
     * @param int|null $expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetIntData')]
    public function testGetInt(Values $values, string $key, ?int $alt, ?int $expected): void
    {
        $this->assertSame($expected, $values->getInt($key, $alt));
    }

    /**
     * Data provider for `testGetInt`.
     *
     * @return iterable
     */
    public static function getTestGetIntData(): iterable
    {
        $values = new Values(self::TEST_VALUES);
        yield 'true'                      => [$values, 'true', null, null];
        yield 'false'                     => [$values, 'false', null, null];
        yield 'numeric 1'                 => [$values, '1', null, 1];
        yield 'numeric 11'                => [$values, '11', null, 11];
        yield 'numeric -11'               => [$values, '-11', null, -11];
        yield 'numeric 0'                 => [$values, '0', null, 0];
        yield 'numeric 0.5 (cast to int)' => [$values, '0.5', null, 0];
        yield 'truthy'                    => [$values, 'truthy', null, null];
        yield 'falsy'                     => [$values, 'falsy', null, null];
        yield 'neither'                   => [$values, 'neither', null, null];
        yield 'key does not exist'        => [$values, 'null', null, null];
        yield 'key does not exist: alt'   => [$values, 'null', 1, 1];
    }

    /**
     *
     * @param Values     $values
     * @param string     $key
     * @param float|null $alt
     * @param float|null $expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetFloatData')]
    public function testGetFloat(Values $values, string $key, ?float $alt, ?float $expected): void
    {
        $this->assertSame($expected, $values->getFloat($key, $alt));
    }

    /**
     * Data provider for `testGetFloat`.
     *
     * @return iterable
     */
    public static function getTestGetFloatData(): iterable
    {
        $values = new Values(self::TEST_VALUES);
        yield 'true'                      => [$values, 'true', null, null];
        yield 'false'                     => [$values, 'false', null, null];
        yield 'numeric 1'                 => [$values, '1', null, 1];
        yield 'numeric 11'                => [$values, '11', null, 11];
        yield 'numeric -11'               => [$values, '-11', null, -11];
        yield 'numeric 0'                 => [$values, '0', null, 0];
        yield 'numeric 0.5'               => [$values, '0.5', null, 0.5];
        yield 'truthy'                    => [$values, 'truthy', null, null];
        yield 'falsy'                     => [$values, 'falsy', null, null];
        yield 'neither'                   => [$values, 'neither', null, null];
        yield 'key does not exist'        => [$values, 'null', null, null];
        yield 'key does not exist: alt'   => [$values, 'null', 1.5, 1.5];
    }
}
