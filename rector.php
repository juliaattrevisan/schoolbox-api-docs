<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php83\Rector\FuncCall\CombineHostPortLdapUriRector;
use Rector\Php83\Rector\FuncCall\RemoveGetClassGetParentClassNoArgsRector;
use Rector\Php84\Rector\Class_\DeprecatedAnnotationToDeprecatedAttributeRector;
use Rector\Php84\Rector\FuncCall\AddEscapeArgumentRector;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;

// Current Rector rule:
// update to PHP 8.4, by including all PHP 8.3 and 8.4 rules
// (setting withPHPSets introduces too many changes)
// See https://getrector.com/find-rule?rectorSet=php-php-83&activeRectorSetGroup=php,
// https://getrector.com/find-rule?rectorSet=php-php-84&activeRectorSetGroup=php
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
    ->withRules([
        // Select PHP 8.3 rules
        CombineHostPortLdapUriRector::class,
        RemoveGetClassGetParentClassNoArgsRector::class,

        // Select PHP 8.4 rules
        AddEscapeArgumentRector::class,
        ExplicitNullableParamTypeRector::class,
        DeprecatedAnnotationToDeprecatedAttributeRector::class,
    ])
    ->withTypeCoverageLevel(0)
;
