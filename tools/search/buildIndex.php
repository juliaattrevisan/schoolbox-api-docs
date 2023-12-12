#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Search\Api\Database\ResultType;
use Schoolbox\Search\Index;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Rebuild search index
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        // Build mappers and search classes
        $indexMapper         = Index\Mapper::getInstance();
        $indexBuilders       = [
            new ResultType\Assessment\IndexBuilder($this->getDatabaseInternal()),
            new ResultType\Badge\IndexBuilder($this->getDatabaseInternal()),
            new ResultType\Blog\IndexBuilder($this->getDatabaseInternal()),
            new ResultType\Event\IndexBuilder($this->getDatabaseInternal()),
            new ResultType\File\IndexBuilder($this->getDatabaseInternal()),
        ];
        $indexTextMapper     = Index\Text\Mapper::getInstance();
        $indexFulltextMapper = Index\Fulltext\Mapper::getInstance();

        // Actually create data
        // TODO the final version won't truncate: instead, it'll override?
        $indexMapper->truncate();
        foreach ($indexBuilders as $indexBuilder) {
            foreach ($indexBuilder->buildUpdates(Alaress_Date_Format::now()->addYears(-5)) as $item) {
                $indexMapper->save($item);
                $indexTextMapper->save($item->getTextIndices());
                $indexFulltextMapper->save($item->getFulltextIndices());
            }
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
