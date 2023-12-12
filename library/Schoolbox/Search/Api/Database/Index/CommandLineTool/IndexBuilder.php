<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index\CommandLineTool;

use Alaress_Date_Format;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Search\Api\Database\Index\IBuilder;
use Schoolbox\Search\Api\Database\ResultType;
use Schoolbox\Search\Index;

/**
 * Command-line tool for rebuilding search indices.
 */
class IndexBuilder extends App
{
    /**
     * @inheritdoc
     */
    public function run(): void
    {
        // There's no point indexing any result type without a search on a text
        // field. Thus, the following result types are not pre-indexed:
        // * Pages
        // * Tiles
        // * Users
        /** @var array<IBuilder> $indexBuilders */
        $indexBuilders       = [
            new ResultType\Assessment\IndexBuilder($this->getDatabaseInternal(), $this->getLogger()),
            new ResultType\Badge\IndexBuilder($this->getDatabaseInternal(), $this->getLogger()),
            new ResultType\Blog\IndexBuilder($this->getDatabaseInternal(), $this->getLogger()),
            new ResultType\Event\IndexBuilder($this->getDatabaseInternal(), $this->getLogger()),
            new ResultType\File\IndexBuilder($this->getDatabaseInternal(), $this->getLogger()),
            new ResultType\Forum\IndexBuilder($this->getDatabaseInternal(), $this->getLogger()),
            new ResultType\Link\IndexBuilder($this->getDatabaseInternal(), $this->getLogger()),
            new ResultType\News\IndexBuilder($this->getDatabaseInternal(), $this->getLogger()),
            new ResultType\Textbox\IndexBuilder($this->getDatabaseInternal(), $this->getLogger()),
        ];
        $indexMapper         = Index\Mapper::getInstance();
        $indexTextMapper     = Index\Text\Mapper::getInstance();
        $indexFulltextMapper = Index\Fulltext\Mapper::getInstance();

        // Run for as long as you need to?
        $this->getDatabaseInternal()->execute('SET MAX_STATEMENT_TIME=0');

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
}
