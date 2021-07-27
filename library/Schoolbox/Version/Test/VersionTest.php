<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Version\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Config\TestLoader;
use Schoolbox\Version\Version;

/**
 * Tests for checking the version of the running instance
 *
 * @covers \Schoolbox\Version\Version
 */
class VersionTest extends TestCase
{

    /**
     * Confirm we are able to extract the major section from the full site_version
     *
     * @dataProvider getTestGetMajorData
     * @covers       \Schoolbox\Version\Version::getMajor
     *
     * @param array       $config
     * @param string|null $expected
     */
    public function testGetMajor(?string $expected, array $config): void
    {
        $factoryVersion = new Version(new TestLoader($config));
        $this->assertSame($expected, $factoryVersion->getMajor());
    }

    /**
     * Data provider for `testGetMajor`
     *
     * @return iterable
     */
    public function getTestGetMajorData(): iterable
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
     * @dataProvider getTestGetMinorData
     * @covers       \Schoolbox\Version\Version::getMinor
     *
     * @param string|null $expected
     * @param array       $config
     */
    public function testGetMinor(?string $expected, array $config): void
    {
        $factoryVersion = new Version(new TestLoader($config));
        $this->assertSame($expected, $factoryVersion->getMinor());
    }

    /**
     * Data provider for `testGetMinor`
     * @return iterable
     */
    public function getTestGetMinorData(): iterable
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
     * @dataProvider getTestIsClientHostedData
     * @covers       \Schoolbox\Version\Version::isClientHosted
     *
     * @param bool  $expected
     * @param array $config
     */
    public function testIsClientHosted(?bool $expected, array $config): void
    {
        $factoryVersion = new Version(new TestLoader($config));
        $this->assertEquals($expected, $factoryVersion->isClientHosted());
    }

    /**
     * Data provider for `testIsClientHosted`
     *
     * @return iterable
     */
    public function getTestIsClientHostedData(): iterable
    {
        yield 'match client hosted is true' => [
            true,
            [
                'site_version'     => '21.0.2-1',
                'site_environment' => 'client-hosted',
            ],
        ];

        yield 'match client hosted is false' => [
            false,
            [
                'site_version'     => '21.0.2-1',
                'site_environment' => 'cloud-hosted',
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
     * @dataProvider getTestIsCloudHostedData(): iterable
     * @covers       \Schoolbox\Version\Version::isCloudHosted
     *
     * @param bool|null $expected
     * @param array     $config
     */
    public function testIsCloudHosted(?bool $expected, array $config): void
    {
        $factoryVersion = new Version(new TestLoader($config));
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
                'site_environment' => 'cloud-hosted',
            ],
        ];

        yield 'match cloud hosted is false' => [
            false,
            [
                'site_version'     => '21.0.2-1',
                'site_environment' => 'client-hosted',
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

    /**
     * @dataProvider getTestShowUpgradeReminderData
     * @covers       \Schoolbox\Version\Version::showUpgradeReminder
     */
    public function testShowUpgradeReminder(?bool $expected, array $config): void
    {
        $factoryVersion = new Version(new TestLoader($config));
        $this->assertEquals($expected, $factoryVersion->showUpgradeReminder());
    }

    /**
     * Data provider for `testShowUpgradeReminderData`
     * @return iterable
     * @todo InstanceRegistry calls are not handled and need better coverage
     *
     */
    public function getTestShowUpgradeReminderData(): iterable
    {
        yield 'cloud hosted never needs a upgrade reminder' => [
            false,
            [
                'site_version'     => '21.0.2-1',
                'site_environment' => 'cloud-hosted',
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
