<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Version\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\Version\Version;

/**
 * Tests for checking the version of the running instance
 *
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Version\Version::class)]
class VersionTest extends TestCase
{
    /**
     * Confirm we are able to extract the major section from the full site_version
     *
     *
     * @param array       $config
     * @param string|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetMajorData')]
    public function testGetMajor(?string $expected, array $config): void
    {
        $factoryVersion = new Version(new ArrayLoader($config));
        $this->assertSame($expected, $factoryVersion->getMajor());
    }

    /**
     * Data provider for `testGetMajor`
     *
     * @return iterable
     */
    public static function getTestGetMajorData(): iterable
    {
        yield 'match major version 21.1' => [
            '21.0',
            [
                'site_version' => '21.0.2-1',
            ],
        ];

        yield 'match major version 20.3' => [
            '20.3',
            [
                'site_version' => '20.3.beta6',
            ],
        ];

        yield 'match major version 20.2' => [
            '20.2',
            [
                'site_version' => '20.2.11-1alpha',
            ],
        ];
    }

    /**
     * Confirm we are able to extract the minor section from the full site_version
     *
     *
     * @param string|null $expected
     * @param array       $config
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetMinorData')]
    public function testGetMinor(?string $expected, array $config): void
    {
        $factoryVersion = new Version(new ArrayLoader($config));
        $this->assertSame($expected, $factoryVersion->getMinor());
    }

    /**
     * Data provider for `testGetMinor`
     * @return iterable
     */
    public static function getTestGetMinorData(): iterable
    {
        yield 'match minor version 2-1' => [
            '2-1',
            [
                'site_version' => '21.0.2-1',
            ],
        ];

        yield 'match minor version beta6' => [
            'beta6',
            [
                'site_version' => '20.2.beta6',
            ],
        ];

        yield 'match minor version 11-1alpha' => [
            '11-1alpha',
            [
                'site_version' => '20.2.11-1alpha',
            ],
        ];
    }

    /**
     * Confirm we can accurately identify a client hosted instance
     *
     *
     * @param bool  $expected
     * @param array $config
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsClientHostedData')]
    public function testIsClientHosted(?bool $expected, array $config): void
    {
        $factoryVersion = new Version(new ArrayLoader($config));
        $this->assertEquals($expected, $factoryVersion->isClientHosted());
    }

    /**
     * Data provider for `testIsClientHosted`
     *
     * @return iterable
     */
    public static function getTestIsClientHostedData(): iterable
    {
        yield 'match client hosted is true' => [
            true,
            [
                'site_version'     => '21.0.2-1',
                'site_environment' => Version::SITE_ENVIRONMENT_CLIENT_HOSTED,
            ],
        ];

        yield 'match client hosted is false' => [
            false,
            [
                'site_version'     => '21.0.2-1',
                'site_environment' => Version::SITE_ENVIRONMENT_CLOUD_SAAS,
            ],
        ];

        yield 'match client hosted is unknown' => [
            false,
            [
                'site_version'     => '21.0.2-1',
                'site_environment' => 'super-unknown',
            ],
        ];
    }

    /**
     * Confirm we can accurately identify a cloud hosted instance
     *
     *
     * @param bool|null $expected
     * @param array     $config
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsCloudHostedData(): iterable')]
    public function testIsCloudHosted(?bool $expected, array $config): void
    {
        $factoryVersion = new Version(new ArrayLoader($config));
        $this->assertEquals($expected, $factoryVersion->isCloudHosted());
    }

    /**
     * Data provider for `testIsCloudHosted`
     *
     * @return iterable
     */
    public function getTestIsCloudHostedData(): iterable
    {
        yield 'match cloud hosted is true' => [
            true,
            [
                'site_version'     => '21.0.2-1',
                'site_environment' => Version::SITE_ENVIRONMENT_CLOUD_SAAS,
            ],
        ];

        yield 'match cloud hosted is false' => [
            false,
            [
                'site_version'     => '21.0.2-1',
                'site_environment' => Version::SITE_ENVIRONMENT_CLIENT_HOSTED,
            ],
        ];

        yield 'match cloud hosted is unknown' => [
            false,
            [
                'site_version'     => '21.0.2-1',
                'site_environment' => 'a-walking-potato',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getTestShowUpgradeReminderData')]
    public function testShowUpgradeReminder(?bool $expected, array $config): void
    {
        $factoryVersion = new Version(new ArrayLoader($config));
        $this->assertEquals($expected, $factoryVersion->showUpgradeReminder());
    }

    /**
     * Data provider for `testShowUpgradeReminderData`
     * @return iterable
     * @todo InstanceRegistry calls are not handled and need better coverage
     *
     */
    public static function getTestShowUpgradeReminderData(): iterable
    {
        yield 'cloud hosted never needs a upgrade reminder' => [
            false,
            [
                'site_version'     => '21.0.2-1',
                'site_environment' => Version::SITE_ENVIRONMENT_CLOUD_SAAS,
            ],
        ];

        yield 'non client hosting never needs a upgrade reminder' => [
            false,
            [
                'site_version'     => '21.0.2-1',
                'site_environment' => 'unknown-hosted',
            ],
        ];
    }
}
