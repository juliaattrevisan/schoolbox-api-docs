<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Url\Test;

use Psr\Http\Message\UriInterface;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\Url\Factory;
use PHPUnit\Framework\TestCase;
use Schoolbox\Url\Uri;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Url\Factory::class)]
class FactoryTest extends TestCase
{
    /**
     * @param UriInterface $expected
     * @param array        $config
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestBuildFullyQualifiedData')]
    public function testBuildFullyQualified(UriInterface $expected, array $config): void
    {
        $configLoader = new ArrayLoader($config);
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
    public static function getTestBuildFullyQualifiedData(): iterable
    {
        return [
            'no site_hostname, no site_domain' => [
                (new Uri())->withHost(''),
                [],
            ],
            'empty site_hostname, empty site_domain' => [
                (new Uri())->withHost(''),
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
