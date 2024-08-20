<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 * Walks the object tree and restores soft-deleted branches.
 * Does not force the restoration through non-deleted nodes.
 *
 * Limitations:
 * * Does not restore assessments in external markbook @see SubmissionBoxMapper::deleteFromMarkbook()
 * * Requires manual update of the soft-delete registry stored in this class (potentially out of sync with code-base)
 * * Restores EVERYTHING below specified IDs. Does NOT examine timestamps to see if the deleted item was part of delete cascade
 *
 * Usage: php ./RecursiveUndeleter.php -m{mapperClass} -i{ids} [-w{seconds}] [-v] [-p]
 *        php ./RecursiveUndeleter.php --mapper {mapperClass} --ids {ids} --window {seconds} [--verbose] [--perform]
 *
 *  ShortOpt | LongOpt
 *  -p       | --perform   *without* this flag, un-delete will only perform a dry-run
 *  -m       | --mapper    which mapper class does the root entity belong to?
 *  -i       | --id        Comma-separated list of IDs to un-delete, eg '1' or '1,2,3'. Use '*' (including quotes) to get all.
 *  -w       | --window    duration in seconds in which to match deletedAt dates. Children deleted outside span will not be restored.
 *  -v       | --verbose   verbosity:   how much activity will be logged.
 */

namespace Schoolbox\Mapper;

use Alaress\Database\Connector\IConnector;
use Alaress\Lumberjack\Logger;
use Alaress_Date_Format;
use PDOException;
use Schoolbox\Blog;
use Schoolbox\Collection;
use Schoolbox\Database\Factory;
use Schoolbox\Discussion;
use Schoolbox\IMapper;
use Schoolbox\Fixtures;
use Schoolbox\ResourceBooking;
use Schoolbox\Application\CliTool;
use Throwable;

use function count;
use function get_class;
use function max;
use function method_exists;
use function min;
use function preg_replace;
use function sprintf;
use function str_pad;
use function str_repeat;
use function strlen;
use function strpos;

class RecursiveUndeleterCli extends CliTool\App
{
    // To account for legacy non-synchronised deletedAt timestamps, how much allowance should we consider as being
    // part of same action?
    private const DEFAULT_DELETED_AT_WINDOW = 120; // default to 1 minute either side of root object's soft-delete timestamp

    /** @var string */
    private $rootTable;

    /** @var array the ids that were supplied in a command-line argument (array of integers or ['*']) */
    private $rootIds;

    /** @var IConnector */
    private IConnector $database;

    /** @var Logger */
    private $logger;

    /**
     * Period in seconds, centered on parent deletedAt timestamp, that child models must be deleted within
     * to be considered part of the same deletion action.
     *
     * @var int
     */
    private $deletedAtWindow;


    /**
     * Command-line specified date to pretend the indicated root objects were deleted at.
     *
     * This allows us to specify a center of window restoration for:
     * * objects that do not have a date-format soft-delete field, or
     * * objects that are not soft-deleted at all,
     *
     * If a root object has a soft-delete date value, and this date was specified, then this
     * date will override the database's soft-delete date value.
     *
     * @var null|Alaress_Date_Format
     */
    private $explicitDeletionDate;


    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return ['t:', 'i:', 'd:', 'w:'];
    }


    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return ['table:', 'id:', 'override:', 'window:'];
    }


    /** @inheritDoc */
    public function run(): void
    {
        // TODO update all usages of '$this->logger' to '$this->getLogger()', this is an interim measure to limit the size of this PR
        $this->logger = $this->getLogger();

        try {
            $this->parseCommandLineArgs();
            $this->connectToDatabase();

            $recursiveUndeleter = new RecursiveUndeleter($this->logger, $this->deletedAtWindow, $this->explicitDeletionDate);

            $failureReason = $this->checkThatUndeletionIsPossible($recursiveUndeleter);

            if ($failureReason !== '') {
                $this->logger->error($failureReason);
                exit(1);
            }

            $modelsByMapperClassByRootMapperClass = $this->getDataStructureForAllRootModels(
                $this->rootIds,
                $recursiveUndeleter
            );

            foreach ($modelsByMapperClassByRootMapperClass as $rootMapperClass => $modelsByMapperClass) {
                $this->printResults("Root mapper: $rootMapperClass", $modelsByMapperClass);
                $this->printDeletedAtDates($modelsByMapperClass);
            }

            if ($this->perform()) {
                $this->database->beginTransaction();
                foreach ($modelsByMapperClassByRootMapperClass as $rootMapperClass => $modelsByMapperClass) {
                    try {
                        $this->logger->info(sprintf("--- Restoring %s models ---", $rootMapperClass));
                        $recursiveUndeleter->restoreSoftDeletedModels($modelsByMapperClass);
                    } catch (Throwable $e) {
                        $this->logger->critical("Unable to restore models, rolling back.", ['exception' => $e]);
                        $this->logger->error($e->getTraceAsString());
                        $this->database->rollBack();
                        exit(1);
                    }
                }
                $this->database->commit();
            }
        } catch (PDOException $e) {
            $this->logger->error($e->getMessage());
            $helpMsg =
                "Most likely this is because the foreign key has a non-standard naming convention.\n" .
                "Standard naming is `{child_table}`.`{parent_table}_{parent_table_identifier}`.\n" .
                'If this is not the case, you need to specify the join column manually in the soft-delete registry.';
            $this->logger->error($helpMsg);
        } catch (Throwable $e) {
            if ($this->logger instanceof Logger) {
                $this->logger->error($e->getMessage());
            } else {
                echo "{$e->getMessage()}\n\n";
            }
            exit(1);
        }
    }


    /**
     * Establish database connection
     */
    private function connectToDatabase(): void
    {
        $this->database = Factory::getInstanceInternal();
    }


    /**
     * Check as best we can that all requirements are met for the undeletion process.
     * If so, return a blank string. If not, return the reason why not as a string.
     *
     * @param RecursiveUndeleter $recursiveUndeleter
     * @return String
     */
    private function checkThatUndeletionIsPossible(RecursiveUndeleter $recursiveUndeleter): string
    {
        $rootMappers = $recursiveUndeleter->loadMappers($this->rootTable);

        foreach ($rootMappers as $mapper) {
            if (!$mapper->getIdentifierColumn()) {
                return sprintf("Unable to find all IDs for root mapper '%s'. No ID column.", get_class($mapper));
            }
        }

        return '';
    }


    /**
     * Given the list of ids that were supplied on the cammand-line, generate and return the data structure specified below.
     * Log error and exit if specified mapper does not have an ID field registered.
     *
     * Eg. If ids are supplied for the `assess` table, those ids might correspond to Quiz or Survey objects, since
     *     both Quizzes and Surveys are defined in the `assess` table.
     *     So in that case, $modelsByMapperClassByRootMapperClass will contain two key:value pairs, like so:
     *     [
     *         'Schoolbox\Learning\Quiz\Mapper' => [
     *             'ChildClass1' => [Madel1, Model2, ...],
     *             'ChildClass2' => [Model1, Model2, ...],
     *             ...
     *         ],
     *         'Schoolbox\Survey\Mapper' => [
     *             'ChildClass1' => [Madel1, Model2, ...],
     *             'ChildClass2' => [Model1, Model2, ...],
     *             ...
     *         ]
     *     ]
     *     If only one type of object is defined by rows in the table, the array will contain only one key:value pair.
     *
     * @return array<class-string, array<class-string, Collection|IMapper[]>>
     */
    private function getDataStructureForAllRootModels($rootIds, RecursiveUndeleter $recursiveUndeleter): array
    {
        $this->logger->debug("Examining IDs argument to see if user requested all ('*') root objects");

        $allIdsRequested = (1 === count($rootIds) && '*' === $rootIds[0]);

        if (!$allIdsRequested) {
            $this->logger->debug("User did not request all ('*') root ids");
        }

        $rootMappers = $recursiveUndeleter->loadMappers($this->rootTable);

        $modelsByMapperClassByRootMapperClass = [];
        foreach ($rootMappers as $mapper) {
            $modelsByMapperClassByRootMapperClass[get_class($mapper)] = $recursiveUndeleter->findDeletedModels(
                $mapper,
                ($allIdsRequested ? $mapper->findAllIds(['deleted' => true]) : $rootIds)
            );
        }

        return $modelsByMapperClassByRootMapperClass;
    }


    /**
     * Either successfully load all command line args, or print and error message and exit
     */
    private function parseCommandLineArgs(): void
    {
        $errors = [];
        $errors = $this->getCommandLineExplicitDeletionDate($errors);
        $errors = $this->getCommandLineTable($errors);
        $errors = $this->getCommandLineIds($errors);
        $errors = $this->getCommandLineTimestampWindow($errors);

        $red    = self::COLOUR_RED;
        $green  = self::COLOUR_GREEN;
        $white  = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;

        $dateFormat = Alaress_Date_Format::DATABASE_FORMAT;

        if (count($errors)) {
            echo "\n{$red}Invalid syntax. You must: \n";
            foreach ($errors as $error) {
                echo "\t- $error\n";
            }

            $file  = $this->getToolFilename();
            $class = __CLASS__;
            $table = 'folder';

            $helpMsg = "{$white}
NAME
       $class - recursively undelete a graph of models in the database

SYNOPSIS
       {$green}$file {$yellow}[OPTION]...{$white}

DESCRIPTION
       Recursively undelete a graph of models in the database.

       Mandatory arguments to long options are mandatory for short options too.

       {$yellow}-i, --id=ID...{$white}
              comma-separated list of IDs to un-delete, eg '1' or '1,2,3'. Use '*' (including quotes) to get all.
              If any entity with a specified ID is not soft-deleted, it will be ignored.

       {$yellow}-t, --table=TABLE{$white}
              which database table does the root entity reside in?

       {$yellow}-p, --perform{$white}
              *without* this flag, un-delete will only perform a dry-run

       {$yellow}-d, --override=OVERRIDE_DATE{$white}
              Format: {$yellow}'{$dateFormat}'{$white}
              If specified, treat specified root objects as having been deleted at this date, regardless of when they
              were really deleted, whether they have a boolean soft-delete field, or whether they are deleted at all.
              Note, this does NOT mean restore root objects deleted around this time!

       {$yellow}-w, --window=WINDOW{$white}
              duration in seconds in which to match deletedAt dates. Children deleted outside span will not be restored.

       {$yellow}-v[v[v]]{$white}
              verbosity: log with increasing verbosity.

EXAMPLES:
       1. To undelete a soft-deleted folder with ID = 123:
            {$green}$file {$yellow}-t '$table' -i 123 -p{$white}

       2. To undelete children of a non-soft-deleted folder with ID = 123. Restore children deleted within
          one hour before or after '2000-01-01 00:00:00':
            {$green}$file {$yellow}-t '$table' -i 123 -d '2000-01-01 00:00:00' -w 7200 -p{$white}

";
            echo $helpMsg;
            exit(1);
        }
    }


    /**
     * Get the IDs provided on command line
     *
     * @param string[] $errors
     * @return string[]
     */
    private function getCommandLineIds(array $errors): array
    {
        $red    = self::COLOUR_RED;
        $yellow = self::COLOUR_YELLOW;

        if ($this->getOpt('i:') !== null && $this->getOpt('id:') !== null) {
            $errors[] = "{$red}not specify IDs using both the short '{$yellow}-i{$red}' and long '{$yellow}--id{$red}' option syntax";
            return $errors;
        }

        $idString = null;
        if ($this->getOpt('i:') !== null) {
            $idString = $this->getOpt('i:');
        } elseif ($this->getOpt('id:') !== null) {
            $idString = $this->getOpt('id:');
        } else {
            $errors[] = "{$red}specify IDs using either the short '{$yellow}-i{$red}' or long '{$yellow}--id{$red}' option syntax";
            return $errors;
        }

        if (!is_string($idString)) {
            $errors[] = 'not specify the ID argument multiple times';
            return $errors;
        }

        // Ensure it only contains integers and commas
        if ('*' !== $idString && preg_match('/[^0-9,]/', $idString, $matches)) {
            $errors[] = "only use integers and commas when specifying IDs - no spaces (or '*' (including quotes) for all)";
            return $errors;
        }

        $this->rootIds = explode(',', $idString);

        return $errors;
    }


    /**
     * Get the period in time in which to match deleted_at timestamps provided on command line
     *
     * @param string[] $errors
     * @return string[]
     */
    private function getCommandLineTimestampWindow(array $errors): array
    {
        $red    = self::COLOUR_RED;
        $yellow = self::COLOUR_YELLOW;

        if ($this->getOpt('w:') !== null && $this->getOpt('window:') !== null) {
            $errors[] = "{$red}not specify timestamp window using both the short '{$yellow}-w{$red}' and long '{$yellow}--window{$red}' option syntax";
            return $errors;
        }

        $window = null;
        if ($this->getOpt('w:') !== null) {
            $window = $this->getOpt('w:');
        } elseif ($this->getOpt('window:') !== null) {
            $window = $this->getOpt('window:');
        }

        if (null !== $window && preg_match('/\D+/', $window)) {
            $errors[] = 'use only integers (seconds) when specifying the deletedAt timestamp window width, eg \'120\'';
            return $errors;
        }
        if (null === $window) {
            $this->deletedAtWindow = self::DEFAULT_DELETED_AT_WINDOW;
        } else {
            $this->deletedAtWindow = (int)$window;
        }
        return $errors;
    }


    /**
     * @param string[] $errors
     * @return string[]
     */
    private function getCommandLineTable(array $errors): array
    {
        $red    = self::COLOUR_RED;
        $yellow = self::COLOUR_YELLOW;

        // Did user provide both short and long options?
        if ($this->getOpt('t:') !== null && $this->getOpt('table:') !== null) {
            $errors[] = "{$red}specify database table using either the short '{$yellow}-t{$red}' or long '{$yellow}--table{$red}' option syntax, but not both";
            return $errors;
        }
        // Did user provide neither short nor long option
        if ($this->getOpt('t:') === null && $this->getOpt('table:') === null) {
            $errors[] = "{$red}specify database table using either the short '{$yellow}-t{$red}' or long '{$yellow}--table{$red}' option syntax";
            return $errors;
        }

        // What table are we targeting?
        $shortOpt = $this->getOpt('t:');
        $longOpt  = $this->getOpt('table:');

        if (is_string($shortOpt)) {
            $this->rootTable = $shortOpt;
        } elseif (is_string($longOpt)) {
            $this->rootTable = $longOpt;
        } else {
            $errors[] = 'not specify the database table option multiple times';
        }
        return $errors;
    }


    /**
     * Did caller specify to target a specific deletion date
     *
     * @param string[] $errors
     * @return string[]
     */
    private function getCommandLineExplicitDeletionDate(array $errors): array
    {
        $red    = self::COLOUR_RED;
        $yellow = self::COLOUR_YELLOW;

        // Did user provide both short and long options?
        if ($this->getOpt('d:') !== null && $this->getOpt('override:') !== null) {
            $errors[] = "{$red}specify override deletion date using either the short '{$yellow}-d{$red}' or long '{$yellow}--override{$red}' option syntax, but not both";
            return $errors;
        }

        // Did user specify neither?
        if ($this->getOpt('d:') === null && $this->getOpt('override:') === null) {
            return $errors;
        }

        $dateString = $this->getOpt('d:') ?? $this->getOpt('override:');

        try {
            $this->explicitDeletionDate = new Alaress_Date_Format(strtotime($dateString), Alaress_Date_Format::TIMESTAMP);
        } catch (\Exception $e) {
            $this->logger->error("Could not generate a date from string: {$dateString}");
            $this->logger->error($e->getMessage());
            exit(1);
        }

        return $errors;
    }


    /**
     * Print the results with nicely-aligned columns
     *
     * @param array $modelsByMapperClass
     */
    private function printResults($heading, array $modelsByMapperClass): void
    {
        $maxIdFieldWidth = 100; // with two space chars adjacent

        $deDuplicatedResults = $this->buildDeDuplicatedResults($modelsByMapperClass);

        // How wide do columns need to be?
        $maxCountLength       = strlen('COUNT');
        $maxTableLength       = strlen('TABLE');
        $maxMapperClassLength = strlen('MAPPER');
        $maxIdsStringLength   = min($maxIdFieldWidth, strlen('IDS'));

        // Need to iterate first to find widest value in each column
        /** @var IMapper $mapperClass */
        foreach ($deDuplicatedResults as $mapperClass => $models) {
            $count            = (string)count($models);
            $mapper           = $mapperClass::getInstance();
            $table            = $mapper->getTable();
            $rTrimMapperClass = preg_replace('/Mapper$/i', '', $mapperClass);

            $ids = [];
            foreach ($models as $model) {
                $ids[] = $this->buildId($mapper, $model);
            }

            $idsString = '[' . implode(',', $ids) . ']';

            $maxCountLength       = max($maxCountLength, strlen($count));
            $maxTableLength       = max($maxTableLength, strlen($table));
            $maxMapperClassLength = max($maxMapperClassLength, strlen($rTrimMapperClass));
            $maxIdsStringLength   = max($maxIdsStringLength, min($maxIdFieldWidth, strlen($idsString)));
        }

        $reportWidth = 5 + $maxCountLength + $maxTableLength + $maxMapperClassLength + $maxIdsStringLength;

        $runType       = $this->perform() ? 'LIVE RUN' : 'DRY RUN';
        $runTypeLength = strlen($runType);

        $countColumns         = 4;
        $countLeadingHyphens  = (int)(($reportWidth - $runTypeLength - $countColumns) / 2);
        $countTrailingHyphens = $reportWidth - $runTypeLength - $countColumns - $countLeadingHyphens;
        $leadingHyphens       = str_repeat('-', $countLeadingHyphens);
        $trailingHyphens      = str_repeat('-', $countTrailingHyphens);

        $report = "\n$leadingHyphens Results - $runType $trailingHyphens\n";

        // Print table header
        $report .= sprintf("| %-" . ($reportWidth + $countColumns) . "s |\n", $heading);
        $report .= sprintf(
            "| %{$maxCountLength}s | %-{$maxTableLength}s | %-{$maxMapperClassLength}s | %-{$maxIdsStringLength}s |\n",
            'COUNT',
            'TABLE',
            'MAPPER',
            'IDS'
        );

        // Print table rows
        foreach ($deDuplicatedResults as $mapperClass => $models) {
            $count            = (string)count($models);
            $table            = $mapperClass::getInstance()->getTable();
            $rTrimMapperClass = preg_replace('/(\\\)?Mapper$/i', '', $mapperClass);

            // Print the first 3 non-wrapping columns
            $report .= sprintf(
                "| %{$maxCountLength}s | %-{$maxTableLength}s | %-{$maxMapperClassLength}s | [",
                $count,
                $table,
                $rTrimMapperClass
            );

            $countLineCharsAvailable = $maxIdsStringLength - 1;

            $countKeys = count($models->keys());
            foreach ($models->keys() as $idx => $id) {
                $isLast = $countKeys === ($idx + 1);

                $idString = $isLast ? "{$id}" : "{$id},";

                // Do we have room to print this id on this line?
                $idLength = strlen($idString);
                if ($countLineCharsAvailable - $idLength < 0) {
                    // No room. Terminate this line.
                    $report .= str_pad(" |\n", $countLineCharsAvailable + 3, ' ', STR_PAD_LEFT);
                    // print blank first 3 columns
                    $report .= sprintf("| %{$maxCountLength}s | %-{$maxTableLength}s | %-{$maxMapperClassLength}s |  ", '', '', '');
                    // Indicate how many chars are left
                    $countLineCharsAvailable = $maxIdsStringLength - $idLength - 1;
                } else {
                    $countLineCharsAvailable -= $idLength;
                }
                $report .= $idString;
            }
            $report .= ']' . str_pad("|\n", $countLineCharsAvailable + 2, ' ', STR_PAD_LEFT);
        }

        $report .= str_pad('', $maxCountLength + $maxTableLength + $maxMapperClassLength + $maxIdsStringLength + 13, '-');

        $this->logger->info($report);
    }


    /**
     * Print the results with nicely-aligned columns
     *
     * @param array $modelsByMapperClass
     */
    private function printDeletedAtDates(array $modelsByMapperClass): void
    {
        $deDuplicatedResults = $this->buildDeDuplicatedResults($modelsByMapperClass);

        $firstLoop = true;

        /** @var IMapper $mapperClass */
        foreach ($deDuplicatedResults as $mapperClass => $models) {
            $firstModel = $models->first();

            if (!$firstModel) {
                continue;
            }
            if (!method_exists($firstModel, 'getDeletedAt')) {
                $this->logger->info(sprintf("Skipping '%s' because no getDeletedAt() method.", $mapperClass));
                continue;
            }
            $mapper = $mapperClass::getInstance();

            if (!$firstLoop) {
                $this->logger->info('');
            }
            $this->logger->info(sprintf("Deleted at values for table '%s' (mapper '%s'):", $mapper->getTable(), $mapperClass));

            // Align ID columns. Right-align numeric, left align textual.
            $longestId  = 0;
            $rightAlign = true;
            foreach ($models as $model) {
                $id        = $this->buildId($mapper, $model);
                $longestId = max($longestId, strlen($id));
                if ((string)(int)$id !== $id) {
                    $rightAlign = false;
                }
            }

            $alignment = $rightAlign ? '' : '-';
            foreach ($models as $model) {
                $id = $this->buildId($mapper, $model);
                /** @var Alaress_Date_Format $adf */
                $adf = $model->getDeletedAt();
                $this->logger->info(sprintf("%{$alignment}{$longestId}s - %s", $id, $adf->getDatabase()));
            }
            $firstLoop = false;
        }
    }


    /**
     * @param IMapper $mapper
     * @param object  $model
     * @return string
     */
    private function buildId(IMapper $mapper, $model): string
    {
        // Does this model have an identifier?
        if ($mapper->getIdentifierColumn()) {
            return (string)$mapper->getModelProperty($model, $mapper->getIdentifierProperty());
        }

        // No identifier, build our own using all but the 'deletedAt' field.
        $mapping = $mapper->getMap();
        $id      = '';
        $first   = true;
        foreach ($mapping as $map) {
            if (!isset($map['property'])) {
                continue;
            }
            // Ignore soft-delete fields
            if (false !== strpos(strtolower($map['property']), 'deleted')) {
                continue;
            }
            $property = $map['property'];
            if (!$first) {
                $id .= '_';
            }
            $id   .= $mapper->getModelProperty($model, $property);
            $first = false;
        }
        return $id;
    }


    /**
     * It's theoretically possible for our graph to be acyclic, meaning the one logical entity may be present as
     * multiple models.
     *
     * For tables without an ID, we will need to dummy up our own for reporting and de-duplication
     *
     * @param array $modelsByMapperClass
     * @return array
     */
    private function buildDeDuplicatedResults(array $modelsByMapperClass): array
    {
        $deDuplicatedResults = [];
        /** @var class-string<IMapper> $mapperClass */
        foreach ($modelsByMapperClass as $mapperClass => $models) {
            if (!array_key_exists($mapperClass, $deDuplicatedResults)) {
                $deDuplicatedResults[$mapperClass] = new Collection();
            }

            $mapper = $mapperClass::getInstance();
            foreach ($models as $model) {
                $id = $this->buildId($mapper, $model);
                $deDuplicatedResults[$mapperClass][$id] = $model;
            }
        }

        return $deDuplicatedResults;
    }
}
