<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\API\Token\JwtParser\IParser;
use Schoolbox\API\Token\JwtParser\SelfIssued;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\User\Token;
use Schoolbox\UserMapper;

#[CoversClass(SelfIssued::class)]
class SelfIssuedTest extends TestCase
{
    use CreateJwt;
    use CreateLogger;

    private const TEST_ISSUER = 'test.schoolbox.cloud';

    #[DataProvider('getTestAcceptsData')]
    public function testAccepts(array $claims, bool $expected): void
    {
        $this->assertSame($expected, $this->createParser()->accepts($this->createJwt($claims)));
    }

    /**
     * Data provider for `testAccepts`
     *
     * @return iterable
     */
    public static function getTestAcceptsData(): iterable
    {
        yield 'issuer matches' => [
            [IParser::CLAIM_ISS => self::TEST_ISSUER],
            true,
        ];

        yield 'issuer is not set' => [
            [],
            false,
        ];

        yield 'issuer is null' => [
            [IParser::CLAIM_ISS => null],
            false,
        ];

        yield 'issuer does not match' => [
            [IParser::CLAIM_ISS => self::TEST_ISSUER . 'b'],
            false,
        ];
    }

    /**
     * Create the parser.
     *
     * @return SelfIssued
     */
    private function createParser(): SelfIssued
    {
        return new SelfIssued(
            $this->createMock(UserMapper::class),
            new ArrayLoader([
                ConfigMapper::NAME_SITE_HOSTNAME => 'test',
                ConfigMapper::NAME_SITE_DOMAIN   => 'schoolbox.cloud',
            ]),
            $this->createLogger(),
            $this->createMock(Token\Mapper::class)
        );
    }
}
