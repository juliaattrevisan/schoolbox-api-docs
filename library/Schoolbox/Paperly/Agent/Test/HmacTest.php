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
    public function testValidHMAC($sql, $params, $hash, $expected)
    {
        $connection = $this->createMock(ConnectionFactory::class);

        $proxy = new Proxy($connection->build());

        $actual = $proxy->isValidRequest($hash, $sql, $params, self::MOCK_SECRET);

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
               null,
               self::createHash(
                   Proxy::PAPERLY_PROXY_HMAC_ALGO,
                   "SELECT Code,Description FROM luYearLevel",
                   null,
                   self::MOCK_SECRET
               ),
               true
            ],
            'SQL Test 2' => [
                "UPDATE dbo.Students SET EmergencyName = '1TEST2233' WHERE ID IN (?, ?)",
                [1, 2],
                self::createHash(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    "UPDATE dbo.Students SET EmergencyName = '1TEST2233' WHERE ID IN (?, ?)",
                    [1, 2],
                    self::MOCK_SECRET
                ),
                true
            ],
            'SQL Test 3' => [
                "INSERT INTO luYearLevel (Code,Description) VALUES (?, ?)",
                [1, 2],
                self::createHash(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    "INSERT INTO luYearLevel (Code,Description) VALUES (?, ?)",
                    [1, 2],
                    self::MOCK_SECRET
                ),
                true
            ],
            'SQL Test 4' => [
                "(SELECT Code,Description FROM luYearLevel) UNION (SELECT Code,Description FROM luYearLevel WHERE Code = ?)",
                [1],
                self::createHash(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    "(SELECT Code,Description FROM luYearLevel) UNION (SELECT Code,Description FROM luYearLevel WHERE Code = ?)",
                    [1],
                    self::MOCK_SECRET
                ),
                true
            ],
            'SQL Test 5' => [
                "SELECT * FROM luYearLevel",
                null,
                self::createHash(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    "SELECT * FROM luYearLevel",
                    null,
                    self::MOCK_SECRET
                ),
                true
            ]
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidHMAC')]
    public function testInvalidHMAC($sql, $params, $hash, $expected)
    {
        $connection = $this->createMock(ConnectionFactory::class);

        $proxy = new Proxy($connection->build());

        $actual = $proxy->isValidRequest($hash, $sql, $params, self::MOCK_SECRET);

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
                ['a', 'b'],
                self::createHash(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    "SELECT Code, Description FROM luYearLevel",
                    ['a', 'b'],
                    self::MOCK_SECRET
                ),
                false
            ],
            'SQL Test 2' => [
                "DROP TABLE luYearLevel",
                null,
                self::createHash(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    "SELECT * FROM luYearLevel",
                    null,
                    self::MOCK_SECRET
                ),
                false
            ],
            'SQL Test 3 - wrong secret' => [
                "SELECT * FROM luYearLevel",
                null,
                self::createHash(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    "SELECT * FROM luYearLevel",
                    null,
                    'fakesecret'
                ),
                false
            ],
            'SQL Test 4 - wrong algorithm' => [
                "SELECT * FROM luYearLevel",
                null,
                self::createHash(
                    'md5',
                    "SELECT * FROM luYearLevel",
                    null,
                    self::MOCK_SECRET
                ),
                false
            ],
            'SQL Test 5 - mismatched params' => [
                "UPDATE luYearLevel SET Description = ? WHERE Code = ?",
                ['a', 'b'],
                self::createHash(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    "UPDATE luYearLevel SET Description = ? WHERE Code = ?",
                    ['c', 'd'],
                    self::MOCK_SECRET
                ),
                false
            ],
            'SQL Test 6 - payload correct but misordered' => [
                "UPDATE luYearLevel SET Description = ? WHERE Code = ?",
                ['a', 'b'],
                hash_hmac(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    json_encode([
                        'params' => ['a', 'b'],
                        'sql'    => "UPDATE luYearLevel SET Description = ? WHERE Code = ?",
                    ]),
                    self::MOCK_SECRET
                ),
                false
            ],
            'SQL Test 7 - null params not encoded at all' => [
                "SELECT * FROM luYearLevel",
                null,
                hash_hmac(
                    Proxy::PAPERLY_PROXY_HMAC_ALGO,
                    json_encode([
                        'sql' => "SELECT * FROM luYearLevel",
                    ]),
                    self::MOCK_SECRET
                ),
                false
            ],
        ];
    }

    /**
     * Helper method for creating a hash.
     *
     * @param string     $algo
     * @param string     $sql
     * @param array|null $params
     * @param string     $key
     * @return string
     */
    private static function createHash(string $algo, string $sql, ?array $params, string $key): string
    {
        return hash_hmac(
            $algo,
            json_encode(['sql' => $sql, 'params' => $params]),
            $key,
        );
    }
}
