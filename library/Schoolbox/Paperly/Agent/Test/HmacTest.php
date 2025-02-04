<?php

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Paperly\Agent\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Paperly\Agent\ConnectionFactory;
use Schoolbox\Paperly\Agent\Proxy;

class HmacTest extends TestCase
{
    private const MOCK_SECRET = 'AdRr2ROrtEdt2b!';

    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidHMAC')]
    public function testValidHMAC($sql, $hash, $expected)
    {
        $connection = $this->createMock(ConnectionFactory::class);

        $proxy = new Proxy($connection->build());

        $actual = $proxy->isValidRequest($hash, $sql, self::MOCK_SECRET);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Data provider for testValidHMAC
     *
     * @return array
     */
    public static function providerValidHMAC(): array
    {
        return [
            'SQL Test 1' => [
               "SELECT Code,Description FROM luYearLevel",
               hash_hmac(
                   Proxy::PAPERLY_PROXY_HMAC_ALGO,
                   "SELECT Code,Description FROM luYearLevel",
                   self::MOCK_SECRET
               ),
               true
            ],
            'SQL Test 2' => [
                "UPDATE dbo.Students SET EmergencyName = '1TEST2233' WHERE ID IN (?, ?)",
                hash_hmac(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    "UPDATE dbo.Students SET EmergencyName = '1TEST2233' WHERE ID IN (?, ?)",
                    self::MOCK_SECRET
                ),
                true
            ],
            'SQL Test 3' => [
                "INSERT INTO luYearLevel (Code,Description) VALUES (?, ?)",
                hash_hmac(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    "INSERT INTO luYearLevel (Code,Description) VALUES (?, ?)",
                    self::MOCK_SECRET
                ),
                true
            ],
            'SQL Test 4' => [
                "(SELECT Code,Description FROM luYearLevel) UNION (SELECT Code,Description FROM luYearLevel WHERE Code = ?)",
                hash_hmac(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    "(SELECT Code,Description FROM luYearLevel) UNION (SELECT Code,Description FROM luYearLevel WHERE Code = ?)",
                    self::MOCK_SECRET
                ),
                true
            ],
            'SQL Test 5' => [
                "SELECT * FROM luYearLevel",
                hash_hmac(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    "SELECT * FROM luYearLevel",
                    self::MOCK_SECRET
                ),
                true
            ]
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidHMAC')]
    public function testInvalidHMAC($sql, $hash, $expected)
    {
        $connection = $this->createMock(ConnectionFactory::class);

        $proxy = new Proxy($connection->build());

        $actual = $proxy->isValidRequest($hash, $sql, self::MOCK_SECRET);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Data provider for testInvalidHMAC
     *
     * @return array
     */
    public static function providerInvalidHMAC(): array
    {
        return [
            'SQL Test 1' => [
                "UPDATE luYearLevel SET Description = ? WHERE Code = ?",
                hash_hmac(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    "SELECT Code, Description FROM luYearLevel",
                    self::MOCK_SECRET
                ),
                false
            ],
            'SQL Test 2' => [
                "DROP TABLE luYearLevel",
                hash_hmac(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    "SELECT * FROM luYearLevel",
                    self::MOCK_SECRET
                ),
                false
            ],
            'SQL Test 3 - wrong secret' => [
                "SELECT * FROM luYearLevel",
                hash_hmac(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    "SELECT * FROM luYearLevel",
                    'fakesecret'
                ),
                false
            ],
            'SQL Test 4 - wrong algorithm' => [
                "SELECT * FROM luYearLevel",
                hash_hmac(
                    'md5',
                    "SELECT * FROM luYearLevel",
                    self::MOCK_SECRET
                ),
                false
            ]
        ];
    }
}
