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
    public function testAccepts(array $config, array $claims, bool $expected): void
    {
        $this->assertSame($expected, $this->createParser($config)->accepts($this->createJwt($claims)));
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
            [
                IParser::CLAIM_ISS => 'manage.schoolbox.cloud',
            ],
            true,
        ];
    }

    /**
     * Create a parser, with the provided config variables.
     *
     * @param array $config
     * @return InstanceRegistryIssued
     */
    private function createParser(array $config): InstanceRegistryIssued
    {
        return new InstanceRegistryIssued(
            $this->createMock(UserMapper::class),
            new ArrayLoader($config),
            new NullLogger(),
        );
    }
}
