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
use Schoolbox\API\Token\JwtParser\InstanceRegistryIssued;
use Schoolbox\API\Token\JwtParser\IParser;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Url\Uri;
use Schoolbox\UserMapper;

#[CoversClass(InstanceRegistryIssued::class)]
class InstanceRegistryIssuedTest extends TestCase
{
    use CreateJwt;
    use CreateLogger;

    #[DataProvider('getTestAcceptsData')]
    public function testAccepts(array $config, string $instanceId, array $claims, bool $expected): void
    {
        $this->assertSame(
            $expected,
            $this->createParser($config, $instanceId)->accepts($this->createJwt($claims)),
        );
    }

    /**
     * Data provider for `testAccepts`
     *
     * @return iterable
     */
    public static function getTestAcceptsData(): iterable
    {
        $defaultInstanceId          = 'instance';
        $defaultInstanceRegistryUrl = (new Uri())
            ->withScheme('https')
            ->withHost('manage.schoolbox.cloud');

        yield 'exact IR URL match' => [
            [
                ConfigMapper::NAME_INSTANCE_REGISTRY_URL => $defaultInstanceRegistryUrl->__toString(),
            ],
            $defaultInstanceId,
            [
                InstanceRegistryIssued::CLAIM_INSTANCE_ID => $defaultInstanceId,
                IParser::CLAIM_ISS                        => $defaultInstanceRegistryUrl->__toString(),
            ],
            true,
        ];

        yield 'hostname IR URL match' => [
            [
                ConfigMapper::NAME_INSTANCE_REGISTRY_URL => $defaultInstanceRegistryUrl
                    ->withPath('/x')
                    ->__toString(),
            ],
            $defaultInstanceId,
            [
                InstanceRegistryIssued::CLAIM_INSTANCE_ID => $defaultInstanceId,
                IParser::CLAIM_ISS                        => $defaultInstanceRegistryUrl
                    ->withPath('/y')
                    ->__toString(),
            ],
            true,
        ];

        yield 'no issuer in JWT' => [
            [
                ConfigMapper::NAME_INSTANCE_REGISTRY_URL => $defaultInstanceRegistryUrl->__toString(),
            ],
            $defaultInstanceId,
            [
                InstanceRegistryIssued::CLAIM_INSTANCE_ID => $defaultInstanceId,
            ],
            false,
        ];

        yield 'no instance ID in config' => [
            [],
            $defaultInstanceId,
            [
                InstanceRegistryIssued::CLAIM_INSTANCE_ID => $defaultInstanceId,
                IParser::CLAIM_ISS                        => $defaultInstanceRegistryUrl->__toString(),
            ],
            false,
        ];

        yield 'null issuer in JWT' => [
            [
                ConfigMapper::NAME_INSTANCE_REGISTRY_URL => $defaultInstanceRegistryUrl->__toString(),
            ],
            $defaultInstanceId,
            [
                InstanceRegistryIssued::CLAIM_INSTANCE_ID => $defaultInstanceId,
                IParser::CLAIM_ISS                        => null,
            ],
            false,
        ];

        yield 'null instance ID in config' => [
            [
                ConfigMapper::NAME_INSTANCE_REGISTRY_URL => null,
            ],
            $defaultInstanceId,
            [
                InstanceRegistryIssued::CLAIM_INSTANCE_ID => $defaultInstanceId,
                IParser::CLAIM_ISS                        => $defaultInstanceRegistryUrl->__toString(),
            ],
            false,
        ];

        yield 'instance ID mismatch' => [
            [
                ConfigMapper::NAME_INSTANCE_REGISTRY_URL => $defaultInstanceRegistryUrl->__toString(),
            ],
            $defaultInstanceId . 'a',
            [
                InstanceRegistryIssued::CLAIM_INSTANCE_ID => $defaultInstanceId . 'b',
                IParser::CLAIM_ISS                        => $defaultInstanceRegistryUrl->__toString(),
            ],
            false,
        ];

        yield 'hostname IR URL mismatch' => [
            [
                ConfigMapper::NAME_INSTANCE_REGISTRY_URL => $defaultInstanceRegistryUrl
                    ->withHost($defaultInstanceRegistryUrl->getHost() . 'a')
                    ->__toString(),
            ],
            $defaultInstanceId,
            [
                InstanceRegistryIssued::CLAIM_INSTANCE_ID => $defaultInstanceId,
                IParser::CLAIM_ISS                        => $defaultInstanceRegistryUrl
                    ->withHost($defaultInstanceRegistryUrl->getHost() . 'b')
                    ->__toString(),
            ],
            false,
        ];
    }

    /**
     * Create a parser, with the provided config variables and instance ID.
     *
     * @param array  $config
     * @param string $instanceId
     * @return InstanceRegistryIssued
     */
    private function createParser(array $config, string $instanceId): InstanceRegistryIssued
    {
        return new InstanceRegistryIssued(
            $this->createMock(UserMapper::class),
            new ArrayLoader($config),
            $this->createLogger(self::class),
            $instanceId,
        );
    }
}
