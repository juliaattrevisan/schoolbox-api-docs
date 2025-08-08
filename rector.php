<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

// Current Rector rule: update to PHPUnit 11
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/conf',
//        __DIR__ . '/docs',
        __DIR__ . '/library',
        __DIR__ . '/test',
        __DIR__ . '/tools',
//        __DIR__ . '/updates',
        __DIR__ . '/www',
    ])
    ->withPhpSets(
        php84: true,
    )
    ->withTypeCoverageLevel(0)
;
