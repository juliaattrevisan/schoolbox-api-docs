<?php

/**
 * DSNest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Codec\Test;

use DomainException;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Codec\DSN;

/**
 * Class DSNTest
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint\Codec\Test
 */
class DSNTest extends TestCase
{

    /**
     * Test valid DSN strings
     *
     * @dataProvider providerValidDSNDecode
     * @param string $dsn
     * @param array  $expected
     * @throws DomainException
     */
    public function testValidDSNDecode(string $dsn, array $expected): void
    {
        $codec = new DSN();

        $this->assertEquals($expected, $codec->decode($dsn));
    }


    /**
     * Data provider for testValidDSNDecode
     *
     * @return array
     */
    public function providerValidDSNDecode(): array
    {
        return [
            'Valid DSN 1' => [
                'key=qwrSDFh823468;tenant=ABC12345abc',
                [
                    'key' => 'qwrSDFh823468',
                    'tenant' => 'ABC12345abc'
                ]
            ],
        ];
    }


    /**
     * Test invalid DSN strings (these throw DomainExceptions)
     *
     * @dataProvider providerInvalidDSNDecode
     * @param string $dsn
     * @throws DomainException
     *
     */
    public function testInvalidDSNDecode(string $dsn): void
    {
        $this->expectException(DomainException::class);
        $codec = new DSN();
        $codec->decode($dsn);
    }


    /**
     * Data provider for testValidDSNDecode
     * These are all illegal dsn strings
     *
     * @return array
     */
    public function providerInvalidDSNDecode(): array
    {
        return [
            'Invalid DSN 1' => [
                '',
            ],
            'Invalid DSN 2' => [
                'key=',
            ],
            'Invalid DSN 3' => [
                'key=;',
            ],
            'Invalid DSN 4' => [
                'key=;tenant',
            ],
            'Invalid DSN 5' => [
                'key=;tenant=;',
            ],
            'Invalid DSN 6' => [
                'key=tenant=',
            ],
            'Invalid DSN 7' => [
                ';',
            ],
            'Invalid DSN 8' => [
                '    ',
            ],
            'Invalid DSN 9' => [
                '   key=;tenant=',
            ],
            'Invalid DSN 10' => [
                'key=;tenant=     ',
            ],
            'Invalid DSN 11' => [
                'key=haaaaa!;tenant=abc123',
            ],
            'Invalid DSN 12' => [
                'key=aaaaa;tenant=!#4acb',
            ],
            'Invalid DSN 13' => [
                'key=aaaaaa ;tenant=ab c123',
            ],
        ];
    }
}
