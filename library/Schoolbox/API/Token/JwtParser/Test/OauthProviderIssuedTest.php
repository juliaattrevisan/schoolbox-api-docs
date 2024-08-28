<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser\Test;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Schoolbox\API\Token\JwtParser\IParser;
use Schoolbox\API\Token\JwtParser\OauthProviderIssued;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\UserMapper;
use SensitiveParameter;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\API\Token\JwtParser\OauthProviderIssued::class)]
class OauthProviderIssuedTest extends TestCase
{
    /**
     * @param array $config
     * @param array $claims
     * @param bool  $expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestAcceptsData')]
    public function testAccepts(
        array $config,
        array $claims,
        bool $expected,
    ): void {
        $this->assertSame($expected, $this->createParser($config)->accepts($this->createJwt($claims)));
    }

    /**
     * Data provider for `testAccepts`
     *
     * @return iterable
     */
    public static function getTestAcceptsData(): iterable
    {
        // Default configuration + audience:
        // the oAuth provider now requires that the JWT's audience matches the
        // configured client ID.
        $defaultAudience              = 'test';
        $defaultConfigurationClientId = [ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_ID => $defaultAudience];
        $defaultJwtAudience           = [IParser::CLAIM_AUD => $defaultAudience];

        yield 'no issuer configured, no issuer in JWT' => [
            $defaultConfigurationClientId,
                $defaultJwtAudience,
            true,
        ];
        yield 'no issuer configured, null issuer in JWT' => [
            $defaultConfigurationClientId,
                $defaultJwtAudience + [ConfigMapper::NAME_OAUTH_ISSUER => null],
            true,
        ];
        yield 'no issuer configured, issuer in JWT' => [
            $defaultConfigurationClientId,
                $defaultJwtAudience + [IParser::CLAIM_ISS => 'vagrant.dev.schoolbox.cloud'],
            true,
        ];
        yield 'issuer configured, no issuer in JWT' => [
            $defaultConfigurationClientId + [ConfigMapper::NAME_OAUTH_ISSUER => 'vagrant.dev.schoolbox.cloud'],
                $defaultJwtAudience,
            false,
        ];
        yield 'issuer configured, null issuer in JWT' => [
            $defaultConfigurationClientId + [ConfigMapper::NAME_OAUTH_ISSUER => 'vagrant.dev.schoolbox.cloud'],
                $defaultJwtAudience + [ConfigMapper::NAME_OAUTH_ISSUER => null],
            false,
        ];
        yield 'issuer configured, matching issuer in JWT' => [
            $defaultConfigurationClientId + [ConfigMapper::NAME_OAUTH_ISSUER => 'vagrant.dev.schoolbox.cloud'],
                $defaultJwtAudience + [IParser::CLAIM_ISS => 'vagrant.dev.schoolbox.cloud'],
            true,
        ];
        yield 'issuer configured, non-matching issuer in JWT' => [
            $defaultConfigurationClientId + [ConfigMapper::NAME_OAUTH_ISSUER => 'vagrant.dev.schoolbox.cloud'],
                $defaultJwtAudience + [IParser::CLAIM_ISS => 'vagrant.dev.schoolbox.cloudd'],
            false,
        ];

        // null issuer configured: treat same as empty
        yield 'null issuer configured, no issuer in JWT' => [
            $defaultConfigurationClientId + [ConfigMapper::NAME_OAUTH_ISSUER => null],
                $defaultJwtAudience,
            true,
        ];
        yield 'null issuer configured, null issuer in JWT' => [
            $defaultConfigurationClientId + [ConfigMapper::NAME_OAUTH_ISSUER => null],
                $defaultJwtAudience + [IParser::CLAIM_ISS => null],
            true,
        ];
        yield 'null issuer configured, issuer in JWT' => [
            $defaultConfigurationClientId,
                $defaultJwtAudience + [IParser::CLAIM_ISS => 'vagrant.dev.schoolbox.cloud'],
            true,
        ];

        // non-string issuer configured: cast to string
        yield 'non-string issuer configured, no issuer in JWT' => [
            $defaultConfigurationClientId + [ConfigMapper::NAME_OAUTH_ISSUER => 1],
                $defaultJwtAudience,
            false,
        ];
        yield 'non-string issuer configured, matching issuer in JWT' => [
            $defaultConfigurationClientId + [ConfigMapper::NAME_OAUTH_ISSUER => 1],
                $defaultJwtAudience + [IParser::CLAIM_ISS => 1],
            true,
        ];
        yield 'non-string issuer configured, semi-matching issuer in JWT' => [
            $defaultConfigurationClientId + [ConfigMapper::NAME_OAUTH_ISSUER => 1],
                $defaultJwtAudience + [IParser::CLAIM_ISS => '1'],
            true,
        ];
        yield 'non-string issuer configured, non-matching issuer in JWT' => [
            $defaultConfigurationClientId + [ConfigMapper::NAME_OAUTH_ISSUER => 1],
                $defaultJwtAudience + [IParser::CLAIM_ISS => 2],
            false,
        ];

        // non-string issuer in JWT: cast to string
        yield 'no issuer configured, non-string issuer in JWT' => [
            $defaultConfigurationClientId,
                $defaultJwtAudience + [IParser::CLAIM_ISS => 1],
            true,
        ];
        yield 'issuer configured, semi-matching non-string issuer in JWT' => [
            $defaultConfigurationClientId + [ConfigMapper::NAME_OAUTH_ISSUER => '1'],
                $defaultJwtAudience + [IParser::CLAIM_ISS => 1],
            true,
        ];
        yield 'issuer configured, non-matching non-string issuer in JWT' => [
            $defaultConfigurationClientId + [ConfigMapper::NAME_OAUTH_ISSUER => '2'],
                $defaultJwtAudience + [IParser::CLAIM_ISS => 1],
            false,
        ];

        // No configured client ID, or no audience in JWT: reject
        yield 'no configured client ID, no audience in JWT' => [
            [],
            [],
            false,
        ];
        yield 'no configured client ID' => [
            [],
            $defaultJwtAudience,
            false,
        ];
        yield 'no audience in JWT' => [
            $defaultConfigurationClientId,
            [],
            false,
        ];

        // non-matching client ID: reject
        yield 'configured client ID and audience in JWT do not match' => [
            $defaultConfigurationClientId,
            [IParser::CLAIM_AUD => 'not-test'],
            false,
        ];

        // audience may be an array of values
        // (see https://www.rfc-editor.org/rfc/rfc7519#section-4.1.3):
        // if so, JWT is accepted if any match
        yield 'configured client ID matches array audience in JWT' => [
            $defaultConfigurationClientId,
            [IParser::CLAIM_AUD => [$defaultAudience, 'not-test']],
            true,
        ];
        yield 'configured client ID does not match array audience in JWT' => [
            $defaultConfigurationClientId,
            [IParser::CLAIM_AUD => ['not-test', 'also-not-test']],
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
            new ArrayLoader($config),
            $this->createMock(Client::class),
            new ArrayCachePool(null),
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
