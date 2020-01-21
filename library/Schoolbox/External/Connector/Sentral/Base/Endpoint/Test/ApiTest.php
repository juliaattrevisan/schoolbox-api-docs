<?php
/**
 * ApiTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Test;

use DomainException;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\DSNCodec;

/**
 * Class ApiTest
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint\Test
 */
class ApiTest extends TestCase
{

    /**
     * Test valid DSN strings
     *
     * @dataProvider provider_validDSNDecode
     * @param string $dsn
     * @param        $expected
     * @throws DomainException
     */
    public function testValidDSNDecode(string $dsn, $expected): void
    {
        $codec = new DSNCodec();

        $this->assertEquals($expected, $codec->decode($dsn));
    }


    /**
     * Data provider for testValidDSNDecode
     *
     * @return array
     */
    public function provider_validDSNDecode(): array
    {
        return [
            'Valid DSN 1' => [
                'host=http://schoolbox.sentral.school/restapi/v1/;tenant=ABC12345abc',
                [
                    'host' => 'http://schoolbox.sentral.school/restapi/v1/',
                    'tenant' => 'ABC12345abc'
                ]
            ],
        ];
    }


    /**
     * Test invalid DSN strings (these throw DomainExceptions)
     *
     * @dataProvider provider_invalidDSNDecode
     * @param string $dsn
     * @throws DomainException
     * @expectedException DomainException
     */
    public function testInvalidDSNDecode(string $dsn): void
    {
        $codec = new DSNCodec();
        $codec->decode($dsn);
    }


    /**
     * Data provider for testValidDSNDecode
     * These are all illegal dsn strings
     *
     * @return array
     */
    public function provider_invalidDSNDecode(): array
    {
        return [
            'Invalid DSN 1' => [
                '',
            ],
            'Invalid DSN 2' => [
                'host=',
            ],
            'Invalid DSN 3' => [
                'host=;',
            ],
            'Invalid DSN 4' => [
                'host=;tenant',
            ],
            'Invalid DSN 5' => [
                'host=;tenant=;',
            ],
            'Invalid DSN 6' => [
                'host=tenant=',
            ],
            'Invalid DSN 7' => [
                ';',
            ],
            'Invalid DSN 8' => [
                '    ',
            ],
            'Invalid DSN 9' => [
                '   host=;tenant=',
            ],
            'Invalid DSN 10' => [
                'host=;tenant=     ',
            ],
            'Invalid DSN 11' => [
                'host=http://!;tenant=abc123',
            ],
            'Invalid DSN 12' => [
                'host=http://schoolbox.sentral.school;tenant=!#4acb',
            ],
            'Invalid DSN 13' => [
                'host=http:// ;tenant=ab c123',
            ],

            /* Interestingly the host must end with a trailing slash
             * Otherwise sentral tells you "Generic error - Unknown error"
             * which is so extremely unhelpful
             */
            'Invalid DSN 14' => [
                'host=http://schoolbox.sentral.school/restapi/v1',
            ],
        ];
    }
}


