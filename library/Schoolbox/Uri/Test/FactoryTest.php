<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Uri\Test;

use Schoolbox\Config\TestLoader;
use Schoolbox\Uri\Factory;
use PHPUnit\Framework\TestCase;
use Zend\Uri\Uri;
use Zend\Uri\UriInterface;

/**
 * @covers \Schoolbox\Uri\Factory
 */
class FactoryTest extends TestCase
{
    /**
     * @dataProvider getTestBuildFullyQualifiedFromConfigData
     *
     * @param UriInterface $expected
     * @param array        $config
     */
    public function testBuildFullyQualifiedFromConfig(UriInterface $expected, array $config): void
    {
        $factory      = new Factory();
        $configLoader = new TestLoader($config);
        $this->assertSame(
            $expected->getHost(),
            $factory->buildFullyQualifiedFromConfig($configLoader)->getHost(),
        );
    }

    public function getTestBuildFullyQualifiedFromConfigData(): iterable
    {
        return [
            'no site_hostname, no site_domain' => [
                new Uri(''),
                [],
            ],
            'empty site_hostname, empty site_domain' => [
                new Uri(''),
                ['site_hostname' => '', 'site_domain' => '',],
            ],
            'empty site_hostname, non-empty site_domain' => [
                new Uri('https://dev.schoolbox.cloud'),
                ['site_hostname' => '', 'site_domain' => 'dev.schoolbox.cloud',],
            ],
            'non-empty site_hostname, empty site_domain' => [
                new Uri('https://vagrant'),
                ['site_hostname' => 'vagrant', 'site_domain' => '',],
            ],
            'non-empty site_hostname, non-empty site_domain' => [
                new Uri('https://vagrant.dev.schoolbox.cloud'),
                ['site_hostname' => 'vagrant', 'site_domain' => 'dev.schoolbox.cloud',],
            ],
        ];
    }
}
