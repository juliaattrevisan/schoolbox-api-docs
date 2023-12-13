<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index\CommandLineTool;

use Alaress_Date_Format;
use InvalidArgumentException;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Search\Api\Database\Index\IBuilder;
use Schoolbox\Search\Api\Database\ResultType;
use Schoolbox\Search\Index;

/**
 * Command-line tool for rebuilding search indices.
 */
class IndexBuilder extends App
{
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

    private const OPT_LONG_FROM = 'from:';
    private const OPT_LONG_TO = 'to:';

    /**
     * Short options:
     * * -h: help
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP];
    }

    /**
     * Long options:
     * * --help: help
     * * --from: from date
     * * --to:   to date
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP, self::OPT_LONG_FROM, self::OPT_LONG_TO];
    }

    /**
     * @inheritdoc
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        try {
            $fromDate = $this->buildDate($this->getOpt(self::OPT_LONG_FROM));
            $toDate   = $this->buildDate($this->getOpt(self::OPT_LONG_TO));
        } catch (InvalidArgumentException $e) {
            $this->getLogger()->error($e->getMessage());
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        if ($this->perform()) {
            $this->getLogger()->notice('Running in perform mode: search index will be rebuilt');
        } else {
            $this->getLogger()->notice('Running in dry-run mode: provide the `-p` flag to rebuild index');
        }

        $this->getLogger()->notice(
            sprintf(
                'Building search index from %s to %s',
                ($fromDate ? $fromDate->getDatabase() : 'earliest result'),
                ($toDate ? $toDate->getDatabase() : 'most recent result'),
            ),
        );

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
        if ($this->perform()) {
            $indexMapper->truncate();
        }

        foreach ($indexBuilders as $indexBuilder) {
            foreach ($indexBuilder->buildUpdates($fromDate, $toDate) as $item) {
                if (!$this->perform()) {
                    continue;
                }
                $indexMapper->save($item);

                // Propagate item to indices
                $indexTextMapper->save($item->getTextIndices()->map(fn ($index) => $index->setSearchIndex($item)));
                $indexFulltextMapper->save($item->getFulltextIndices()->map(fn ($index) => $index->setSearchIndex($item)));
            }
        }
    }

    /**
     * Print help text
     *
     * @return void
     */
    private function printHelp(): void
    {
        $file       = $this->getToolFilename();
        $green      = self::COLOUR_GREEN;
        $white      = self::COLOUR_WHITE;
        $yellow     = self::COLOUR_YELLOW;
        echo <<<HELP
NAME:
    Search index builder

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    Builds this Schoolbox instance's search indices.

    This script may build indices for only a given date span: it will leave
    indices outside this date span untouched.

OPTIONS
    $yellow-h, --help$white
        Print this help and exit

    $yellow-p, --perform$white
        Actually build search indices: otherwise, only output what would be
        built.

    $yellow--from=FROM_DATE$white
        If provided, only build indices for results from FROM_DATE onward.

        FROM_DATE will be parsed according to the following rules:
        * as a database date (YYYY-MM-DD HH:MM:SS)
        * as a Unix timestamp
        * via `strtotime`

        This script will fail if FROM_DATE is not parseable.

    $yellow--to=TO_DATE$white
        If provided, only build indices for results to TO_DATE and before.

        TO_DATE will be parsed using the same rules as FROM_DATE, and will cause
        this script to fail for the same reasons.


HELP;
    }

    /**
     * Build a date from the value of a command-line argument; will attempt to
     * parse according to the following rules:
     * * as a database date (YYYY-MM-DD HH:MM:SS)
     * * as a Unix timestamp
     * * via `strtotime`
     *
     * @param string|null $date
     * @return Alaress_Date_Format|null
     * @throws InvalidArgumentException if the date could not be parsed
     */
    private function buildDate(?string $date): ?Alaress_Date_Format
    {
        if (isset($date)) {
            try {
                return new Alaress_Date_Format(
                    $date,
                    [
                        Alaress_Date_Format::DATABASE_FORMAT,
                        Alaress_Date_Format::TIMESTAMP
                    ]
                );
            } catch (InvalidArgumentException) {
                // Attempt to parse date via strtotime
                $this->getLogger()->debug(sprintf('Parsing date "%s" using strtotime', $date));
                $strtotime = strtotime($date);
                if ($strtotime) {
                    return new Alaress_Date_Format($strtotime, Alaress_Date_Format::TIMESTAMP);
                } else {
                    throw new InvalidArgumentException(
                        sprintf('Could not parse date "%s" as a database date, timestamp or with strtotime', $date)
                    );
                }
            }
        }
        return null;
    }
}
