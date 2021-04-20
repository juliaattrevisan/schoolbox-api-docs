<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Url\Test;

use Schoolbox\Config\TestLoader;
use Schoolbox\Url\Factory;
use PHPUnit\Framework\TestCase;
use Zend\Uri\Uri;
use Zend\Uri\UriInterface;

/**
 * @covers \Schoolbox\Url\Factory
 */
class FactoryTest extends TestCase
{
    /**
     * @covers \Schoolbox\Url\Factory::buildFullyQualified
     * @dataProvider getTestBuildFullyQualifiedData
     *
     * @param UriInterface $expected
     * @param array        $config
     */
    public function testBuildFullyQualified(UriInterface $expected, array $config): void
    {
        $configLoader = new TestLoader($config);
        $factory      = new Factory($configLoader);
        $this->assertSame(
            $expected->getHost(),
            $factory->buildFullyQualified()->getHost(),
        );
    }

    /**
     * Data provider for `testBuildFullyQualified`
     *
     * @return iterable
     */
    public function getTestBuildFullyQualifiedData(): iterable
    {
        return [
            'no site_hostname, no site_domain' => [
                (new Uri())->setHost(''),
                [],
            ],
            'empty site_hostname, empty site_domain' => [
                (new Uri())->setHost(''),
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
