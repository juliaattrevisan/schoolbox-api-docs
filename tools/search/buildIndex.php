#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Search\Api\Database\ResultType\Assessment\IndexBuilder;
use Schoolbox\Search\Index;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Rebuild search index
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        $mapper       = Index\Mapper::getInstance();
        $indexBuilder = new IndexBuilder($mapper->getDatabase());
        $mapper->truncate();
        foreach ($indexBuilder->buildUpdates(Alaress_Date_Format::now()->addMonths(-1)) as $item) {
            $mapper->save($item);
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
