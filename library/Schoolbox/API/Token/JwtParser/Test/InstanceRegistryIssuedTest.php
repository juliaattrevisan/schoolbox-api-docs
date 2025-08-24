<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\API\Token\JwtParser\Test;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Schoolbox\API\Token\JwtParser\InstanceRegistryIssued;
use Schoolbox\API\Token\JwtParser\IParser;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\UserMapper;

#[CoversClass(InstanceRegistryIssued::class)]
class InstanceRegistryIssuedTest extends TestCase
{
    use CreateJwt;

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
        yield 'basic config' => [
            [
                ConfigMapper::NAME_INSTANCE_REGISTRY_URL => 'manage.schoolbox.cloud',
            ],
            'instance',
            [
                IParser::CLAIM_ISS                        => 'manage.schoolbox.cloud',
                InstanceRegistryIssued::CLAIM_INSTANCE_ID => 'instance',
            ],
            true,
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
            new Logger(
                'InstanceRegistryIssuedTest',
                [new StreamHandler(STDOUT, Level::Debug),],
            ),
            $instanceId,
        );
    }
}
