#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Search\Api\Database\Index\IBuilder;
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
        /** @var array<IBuilder> $indexBuilders */
        $indexBuilders       = [
//            new ResultType\Assessment\IndexBuilder($this->getDatabaseInternal()),
//            new ResultType\Badge\IndexBuilder($this->getDatabaseInternal()),
//            new ResultType\Blog\IndexBuilder($this->getDatabaseInternal()),
//            new ResultType\Event\IndexBuilder($this->getDatabaseInternal()),
//            new ResultType\File\IndexBuilder($this->getDatabaseInternal()),
//            new ResultType\Forum\IndexBuilder($this->getDatabaseInternal()),
//            new ResultType\Link\IndexBuilder($this->getDatabaseInternal()),
//            new ResultType\News\IndexBuilder($this->getDatabaseInternal()),
            new ResultType\Page\IndexBuilder($this->getDatabaseInternal()),
        ];
        $indexTextMapper     = Index\Text\Mapper::getInstance();
        $indexFulltextMapper = Index\Fulltext\Mapper::getInstance();

        // Actually create data
        // TODO the final version won't truncate: instead, it'll override?
        $indexMapper->truncate();
        foreach ($indexBuilders as $indexBuilder) {
            foreach ($indexBuilder->buildUpdates(Alaress_Date_Format::now()->addYears(-10)) as $item) {
                $indexMapper->save($item);

                // Propagate item to indices
                $indexTextMapper->save($item->getTextIndices()->map(fn ($index) => $index->setSearchIndex($item)));
                $indexFulltextMapper->save($item->getFulltextIndices()->map(fn ($index) => $index->setSearchIndex($item)));
            }
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
