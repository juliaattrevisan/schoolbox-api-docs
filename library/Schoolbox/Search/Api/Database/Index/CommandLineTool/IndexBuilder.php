<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index\CommandLineTool;

use Alaress\Database\Exception;
use Alaress_Date_Format;
use InvalidArgumentException;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Search\Api\Database\Index\IBuilder;
use Schoolbox\Search\Api\Database\ResultType;

/**
 * Command-line tool for rebuilding search indices.
 */
class IndexBuilder extends App
{
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

    private const OPT_LONG_FROM = 'from:';
    private const OPT_LONG_TO = 'to:';
    private const OPT_LONG_CLEAR = 'clear';

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
     * * --help:  help
     * * --from:  from date
     * * --to:    to date
     * * --clear: clear index completely
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP, self::OPT_LONG_FROM, self::OPT_LONG_TO, self::OPT_LONG_CLEAR];
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

        // Run for as long as you need to?
        $this->getDatabaseInternal()->execute('SET MAX_STATEMENT_TIME=0');

        if ($this->getOpt(self::OPT_LONG_CLEAR)) {
            $this->getLogger()->notice('Clearing ALL existing indices before rebuilding!');
            if ($this->perform()) {
                $this->getDatabaseInternal()->execute('TRUNCATE search_index');
            }
        }

        // Actually create data
        $updateStatement = $this->getDatabaseInternal()->prepareQuery("
            REPLACE INTO search_index (
                type,
                identifier,
                title,
                date,
                is_relevance_date_affected,
                created_by,
                folder_id,
                fulltext_index
            ) VALUES (
                :type,
                :identifier,
                :title,
                :date,
                :isRelevanceDateAffected,
                :createdBy,
                :folderId,
                :fulltextIndex
            )
        ");
        $deleteStatement = $this->getDatabaseInternal()->prepareQuery("
            DELETE FROM search_index WHERE type = :type AND identifier = :identifier
        ");

        $time = microtime(true);
        foreach ($indexBuilders as $indexBuilder) {
            foreach ($indexBuilder->buildUpdates($fromDate, $toDate) as $updatedIndex) {
                if (!$this->perform()) {
                    continue;
                }

                try {
                    $updateStatement->execute([
                        'type'                    => $updatedIndex->getType(),
                        'identifier'              => $updatedIndex->getIdentifier(),
                        'title'                   => $updatedIndex->getTitle(),
                        'date'                    => $updatedIndex->getDate()?->getDatabase(),
                        'isRelevanceDateAffected' => $updatedIndex->getIsRelevanceDateAffected(),
                        'createdBy'               => $updatedIndex->getCreatedBy(),
                        'folderId'                => $updatedIndex->getFolderId(),
                        'fulltextIndex'           => $updatedIndex->getFulltextIndex(),
                    ]);
                } catch (Exception $e) {
                    $this->getLogger()->warning(
                        sprintf(
                            'Unexpected database error when index for "%s:%s" (%s): ignoring',
                            $updatedIndex->getType(),
                            $updatedIndex->getIdentifier(),
                            $e->getMessage(),
                        )
                    );
                    continue;
                }
            }

            foreach ($indexBuilder->buildDeletes($fromDate, $toDate) as $deletedIndex) {
                if (!$this->perform()) {
                    continue;
                }
                $deleteStatement->execute([
                    'identifier' => $deletedIndex->getIdentifier(),
                    'type'       => $deletedIndex->getType(),
                ]);
            }
        }

        $this->getLogger()->notice(sprintf("Search indices rebuilt in %0.2fs", microtime(true) - $time));
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

    This script may build indices for only a given date span: it will leave indices outside this date span untouched.

OPTIONS
    $yellow-h, --help$white
        Print this help and exit

    $yellow-p, --perform$white
        Actually build search indices: otherwise, only output what would be
        built.

    $yellow--from=FROM_DATE$white
        If provided, only build indices for results from {$yellow}FROM_DATE{$white} onward.

        {$yellow}FROM_DATE{$white} will be parsed according to the following rules:
        * as a database date (YYYY-MM-DD HH:MM:SS)
        * as a Unix timestamp
        * via `strtotime`

        This script will fail if {$yellow}FROM_DATE{$white} is not parseable.

    $yellow--to=TO_DATE$white
        If provided, only build indices for results to {$yellow}TO_DATE{$white} and before.

        {$yellow}TO_DATE{$white} will be parsed using the same rules as {$yellow}FROM_DATE{$white}, and will cause this script to fail for the same reasons.

    $yellow--clear$white
        If provided, clear all existing indices before rebuilding the index.

        Has no effect without the $yellow-p$white or $yellow--perform$white flags.


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
