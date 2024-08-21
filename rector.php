<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;

// Current Rector rule: update to PHPUnit 11
return RectorConfig::configure()
    ->withPaths([
//        __DIR__ . '/conf',
//        __DIR__ . '/dav',
//        __DIR__ . '/docs',
        __DIR__ . '/library',
//        __DIR__ . '/node_modules',
//        __DIR__ . '/packages',
//        __DIR__ . '/test',
//        __DIR__ . '/tools',
//        __DIR__ . '/updates',
//        __DIR__ . '/www',
    ])
//    // uncomment to reach your current PHP version
//    ->withPhpSets()
    ->withSets([PHPUnitSetList::PHPUNIT_100, PHPUnitSetList::PHPUNIT_110,])
    ->withTypeCoverageLevel(0)
;
