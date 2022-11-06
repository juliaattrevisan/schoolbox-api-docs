<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser\Test;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Schoolbox\API\Token\JwtParser\IParser;
use Schoolbox\API\Token\JwtParser\OauthProviderIssued;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\TestLoader;
use Schoolbox\UserMapper;

/**
 * @covers \Schoolbox\API\Token\JwtParser\OauthProviderIssued
 */
class OauthProviderIssuedTest extends TestCase
{
    /**
     * @dataProvider getTestAcceptsData
     * @covers \Schoolbox\API\Token\JwtParser\OauthProviderIssued::accepts
     * @param OauthProviderIssued $parser
     * @param string              $jwt
     * @param bool                $expected
     * @return void
     */
    public function testAccepts(OauthProviderIssued $parser, string $jwt, bool $expected): void
    {
        $this->assertSame($expected, $parser->accepts($jwt));
    }

    /**
     * Data provider for `testAccepts`
     *
     * @return iterable
     */
    public function getTestAcceptsData(): iterable
    {
        yield 'no issuer configured, no issuer in JWT' => [
            $this->createParser([]),
            $this->createJwt([]),
            true,
        ];
        yield 'no issuer configured, null issuer in JWT' => [
            $this->createParser([]),
            $this->createJwt([ConfigMapper::NAME_OAUTH_ISSUER => null]),
            true,
        ];
        yield 'no issuer configured, issuer in JWT' => [
            $this->createParser([]),
            $this->createJwt([IParser::CLAIM_ISS => 'vagrant.dev.schoolbox.cloud']),
            true,
        ];
        yield 'issuer configured, no issuer in JWT' => [
            $this->createParser([ConfigMapper::NAME_OAUTH_ISSUER => 'vagrant.dev.schoolbox.cloud']),
            $this->createJwt([]),
            false,
        ];
        yield 'issuer configured, null issuer in JWT' => [
            $this->createParser([ConfigMapper::NAME_OAUTH_ISSUER => 'vagrant.dev.schoolbox.cloud']),
            $this->createJwt([ConfigMapper::NAME_OAUTH_ISSUER => null]),
            false,
        ];
        yield 'issuer configured, matching issuer in JWT' => [
            $this->createParser([ConfigMapper::NAME_OAUTH_ISSUER => 'vagrant.dev.schoolbox.cloud']),
            $this->createJwt([IParser::CLAIM_ISS => 'vagrant.dev.schoolbox.cloud']),
            true,
        ];
        yield 'issuer configured, non-matching issuer in JWT' => [
            $this->createParser([ConfigMapper::NAME_OAUTH_ISSUER => 'vagrant.dev.schoolbox.cloud']),
            $this->createJwt([IParser::CLAIM_ISS => 'vagrant.dev.schoolbox.cloudd']),
            false,
        ];

        // null issuer configured: treat same as empty
        yield 'null issuer configured, no issuer in JWT' => [
            $this->createParser([ConfigMapper::NAME_OAUTH_ISSUER => null]),
            $this->createJwt([]),
            true,
        ];
        yield 'null issuer configured, null issuer in JWT' => [
            $this->createParser([ConfigMapper::NAME_OAUTH_ISSUER => null]),
            $this->createJwt([IParser::CLAIM_ISS => null]),
            true,
        ];
        yield 'null issuer configured, issuer in JWT' => [
            $this->createParser([]),
            $this->createJwt([IParser::CLAIM_ISS => 'vagrant.dev.schoolbox.cloud']),
            true,
        ];

        // non-string issuer configured: cast to string
        yield 'non-string issuer configured, no issuer in JWT' => [
            $this->createParser([ConfigMapper::NAME_OAUTH_ISSUER => 1]),
            $this->createJwt([]),
            false,
        ];
        yield 'non-string issuer configured, matching issuer in JWT' => [
            $this->createParser([ConfigMapper::NAME_OAUTH_ISSUER => 1]),
            $this->createJwt([IParser::CLAIM_ISS => 1]),
            true,
        ];
        yield 'non-string issuer configured, semi-matching issuer in JWT' => [
            $this->createParser([ConfigMapper::NAME_OAUTH_ISSUER => 1]),
            $this->createJwt([IParser::CLAIM_ISS => '1']),
            true,
        ];
        yield 'non-string issuer configured, non-matching issuer in JWT' => [
            $this->createParser([ConfigMapper::NAME_OAUTH_ISSUER => 1]),
            $this->createJwt([IParser::CLAIM_ISS => 2]),
            false,
        ];

        // non-string issuer in JWT: cast to string
        yield 'no issuer configured, non-string issuer in JWT' => [
            $this->createParser([]),
            $this->createJwt([IParser::CLAIM_ISS => 1]),
            true,
        ];
        yield 'issuer configured, semi-matching non-string issuer in JWT' => [
            $this->createParser([ConfigMapper::NAME_OAUTH_ISSUER => '1']),
            $this->createJwt([IParser::CLAIM_ISS => 1]),
            true,
        ];
        yield 'issuer configured, non-matching non-string issuer in JWT' => [
            $this->createParser([ConfigMapper::NAME_OAUTH_ISSUER => '2']),
            $this->createJwt([IParser::CLAIM_ISS => 1]),
            false,
        ];
    }

    /**
     * Create a parser, with the provided config variables.
     *
     * @param array $config
     * @return OauthProviderIssued
     */
    private function createParser(array $config): OauthProviderIssued
    {
        return new OauthProviderIssued(
            $this->createMock(UserMapper::class),
            new TestLoader($config),
            $this->createMock(Client::class),
        );
    }

    /**
     * Create a mock JWT, with the provided payload.
     *
     * (This test doesn't care about the header or signature: only the payload.)
     *
     * @param array $payload
     * @return string
     */
    private function createJwt(array $payload): string
    {
        // Just use the JWT library's encode methods:
        // for this test we don't care about signing or anything
        return sprintf('.%s.', JWT::urlsafeB64Encode(JWT::jsonEncode($payload)));
    }
}
