<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 *
 * Walks the object tree and restores soft-deleted branches.
 * Does not force the restoration through non-deleted nodes.
 *
 * Limitations:
 * * Does not restore assessments in external markbook @see SubmissionBoxMapper::deleteFromMarkbook()
 * * Requires manual update of the soft-delete registry stored in this class (potentially out of sync with code-base)
 * * Restores EVERYTHING below specified IDs. Does NOT examine timestamps to see if the deleted item was part of delete cascade
 *
 * Usage: php ./RecursiveUndeleter.php -m{mapperClass} -i{ids} [-v] [-p]
 *        php ./RecursiveUndeleter.php --mapper {mapperClass} --ids {ids} [--verbose] [--perform]
 *
 *  ShortOpt | LongOpt
 *  -p       | --perform   perform:     *without* this flag, un-delete will only perform a dry-run
 *  -m       | --mapper    mapperClass: which mapper does the root entity belong to?
 *  -i       | --ids       ids:         Comma-separated list of IDs to un-delete, eg '1' or '1,2,3'. Use '*' to get all.
 *  -v       | --verbose   verbosity:   how much activity will be logged.
 */

use Alaress\Lumberjack\Logger;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\IMapper;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Code as FolderCode;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use \Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Quiz;
use Schoolbox\Learning\Quiz\Section as QuizSection;
use Schoolbox\Learning\Quiz\Section\Question as QuizQuestion;
use Schoolbox\Learning\Quiz\Section\Question\Solution as QuizSolution;
use Schoolbox\Learning\Quiz\Attempt as QuizAttempt;
use Schoolbox\Learning\Quiz\Attempt\Answer as QuizAnswer;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Learning\Assessment\DataModel\Project;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile as SubmissionBoxFile;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile as ReturnedWorkFile;


require_once __DIR__ . '/../library/Bootstrap.php';


$undeleter = new RecursiveUndeleter();
$undeleter->run();

class RecursiveUndeleter
{

    /**
     * If false, identify soft-deleted items and print report, but don't actually restore anything
     *
     * @var bool
     */
    private $perform;

    /** @var string */
    private $rootMapperClass;

    /** @var IMapper */
    private $rootMapper;

    /** @var int */
    private $logThreshold;

    /** @var int[] */
    private $rootIds;

    private $database;

    /** @var Logger */
    private $logger;

    /**
     * Sets of deleted Models, grouped by mapperClass, indexed by ID (or field concat if no ID)
     *
     * @var object[][]|Collection[]
     */
    private $softDeletedModels = [];

    /**
     * Flat registry of soft-delete hooks in system.
     *
     * I can't rely on restoreSoftDeleted() as at the moment it's not comprehensive enough.
     *
     * If joined via simple FK, and FK is named parentTable_parentIdentifier, then you can just list the Mapper (with implicit numeric key).
     * If non-standard naming (eg assess_*), or if joined via join table, then list the join column (string) as key
     *
     * @var string[]|string[][]|IMapper[]|IMapper[][]
     * @todo Ideally this would be dynamically determined by inspecting the post-soft-delete hooks,
     *       but I'd have to parse the mapper's file's text :(
     *
     * @todo For now, duplicate it here manually. long term need to parameterise this data in mappers
     *       so it can be accessed by external classes, or upgrade the restore-soft-deleted to examine
     *       timestamps. That relies on us updating the delete cascade to not increment deleted timestamp
     *       of already deleted items.
     */
    private static $softDeleteRegistry = [
        Folder\Mapper::class            => [
            FolderCode\Mapper::class,
            CourseSpec\Mapper::class,
            UnitSpec\Mapper::class,
            CourseImpl\Mapper::class,
        ],
        FolderCode\Mapper::class        => [],
        CourseSpec\Mapper::class        => [
            UnitSpec\Mapper::class,
        ],
        UnitSpec\Mapper::class          => [
            'v_us.course_unit_specification_id' => Assessment\Mapper::class,
        ],
        CourseImpl\Mapper::class        => [
            UnitImpl\Mapper::class,
        ],
        Assessment\Mapper::class        => [
            Quiz\Mapper::class,
            SubmissionBoxMapper::class,
            TaskMapper::class,
            Project\Mapper::class,
        ],
        UnitImpl\Mapper::class          => [
            'v_ui.course_unit_implementation_id' => Assessment\Mapper::class,
        ],
        Quiz\Mapper::class              => [
            QuizSection\Mapper::class,
            QuizAttempt\Mapper::class,

        ],
        SubmissionBoxMapper::class      => [
            'student_submission.box_id' => SubmissionMapper::class,
            'submission_return.box_id'  => ReturnedWorkMapper::class,
        ],
        TaskMapper::class               => [
            Task\CompletionMapper::class,
        ],
        Task\CompletionMapper::class    => [],
        Project\Mapper::class           => [
            'v_pa.project_id' => Assessment\Mapper::class,
        ],
        QuizSection\Mapper::class       => [
            'assess_question.section_id' => QuizQuestion\Mapper::class,
        ],
        QuizAttempt\Mapper::class       => [
            'assess_response.attempt_id' => QuizAnswer\Mapper::class,
        ],
        SubmissionMapper::class         => [
            SubmissionBoxFile\Mapper::class,
        ],
        ReturnedWorkMapper::class       => [
            ReturnedWorkFile\Mapper::class,
        ],
        QuizQuestion\Mapper::class      => [
            'assess_answer.question_id' => QuizSolution\Mapper::class,
        ],
        QuizAnswer\Mapper::class        => [],
        QuizSolution\Mapper::class      => [],
        SubmissionBoxFile\Mapper::class => [
            Plagscan\Mapper::class,
        ],
        ReturnedWorkFile\Mapper::class  => [],
        Plagscan\Mapper::class          => [],
    ];


    /**
     * Public entry point
     */
    public function run()
    {
        try {
            $this->checkCLI();
            $this->parseCommandLineArgs();
            $this->buildLogger();
            $this->loadRootMapper();
            $this->database = Database::getInstance();
            $this->checkRestoreAll();
            $this->findDeletedModels($this->rootMapper, $this->rootIds);
            $this->printResults($this->softDeletedModels);

            if($this->perform) {
                $this->restoreSoftDeletedModels();
            }
        }
        catch(PDOException $e) {
            $this->logger->error($e->getMessage());
            $helpMsg =
                "Most likely this is because the foreign key has a non-standard naming convention.\n" .
                "Standard naming is `{child_table}`.`{parent_table}_{parent_table_identifier}`.\n" .
                'If this is not the case, you need to specify the join column manually in the soft-delete registry.';
            $this->logger->error($helpMsg);
        }
        catch(Exception $e) {
            // TODO remove this block when we drop PHP5 support
            if($this->logger instanceof Logger) {
                $this->logger->error($e->getMessage());
            }
            else {
                echo "{$e->getMessage()}\n\n";
            }
            exit(1);
        }
        catch(Throwable $e) {
            if($this->logger instanceof Logger) {
                $this->logger->error($e->getMessage());
            }
            else {
                echo "{$e->getMessage()}\n\n";
            }
            exit(1);
        }
    }


    /**
     * If the user specified all IDs ('*') on command line, load all ids for the specified mapper.
     *
     * Log error and exit if specified mapper does not have an ID field registered.
     */
    private function checkRestoreAll()
    {
        $this->logger->debug("Examining IDs argument to see if user requested all ('*') root objects");
        if(1 === count($this->rootIds) && '*' === $this->rootIds[0]) {
            if(!$this->rootMapper->getIdentifierColumn()) {
                $this->logger->error(sprintf("Unable to find all IDs for root mapper '%s'. No ID column", get_class($this->rootMapper)));
                exit(1);
            }
            $this->logger->info("User requested all ('*') root objects. Fetching all root IDs");
            $this->rootIds = $this->rootMapper->findAllIds(['deleted' => true]);
            $this->logger->debug(sprintf('Root IDs updated to %s', $this->getIdString($this->rootIds)));
        }
        else {
            $this->logger->debug("User did not request all ('*') root Ids");
        }
    }


    /**
     * Build a logger with a threshold level dependent on whether user requested verbose mode
     */
    private function buildLogger()
    {
        $this->logger = new Logger(STDOUT, STDERR, $this->logThreshold);
    }


    /**
     * Load an instance of the root mapper
     */
    private function loadRootMapper()
    {
        if(!class_exists($this->rootMapperClass)) {
            echo "Mapper class does not exist: {$this->rootMapperClass}";
            $this->logger->error("Mapper class does not exist: {$this->rootMapperClass}");
            exit(1);
        }
        try {
            /** @var IMapper $mapperClass */
            $mapperClass      = $this->rootMapperClass;
            $this->rootMapper = $mapperClass::getInstance();
        }
        catch(Exception $e) {
            $this->logger->error($e->getMessage());
            exit(1);
        }
    }


    /**
     * Ensure running as CLI and exits if not
     */
    private function checkCLI()
    {
        if(PHP_SAPI !== 'cli') {
            echo 'Must be run from Command Line Interface (CLI) only';
            exit(1);
        }
    }


    /**
     * Either successfully load all command line args, or print and error message and exit
     */
    private function parseCommandLineArgs()
    {
        $opts   = getopt('m:i:vp', ['mapper:', 'id:', 'verbose', 'perform']);
        $errors = [];
        $this->getCommandLinePerform($opts);
        $this->getCommandLineThreshold($opts);
        $this->getCommandLineMapperClass($errors, $opts);
        $this->getCommandLineIds($errors, $opts);

        if(count($errors)) {
            echo "\nInvalid syntax. You must: \n";
            foreach($errors as $error) {
                echo "- $error\n";
            }

            echo "\nExample to undelete folder with ID = 123:\n";
            echo "    php ./RecursiveUndelete.php --mapper 'Schoolbox\Resource\Folder\Mapper' --id 123 -p\n\n";
            exit(1);
        }
    }


    /**
     * Get the IDs provided on command line
     *
     * @param string[] $errors
     * @param array    $opts
     */
    private function getCommandLineIds(&$errors, array $opts)
    {
        if(isset($opts['i'], $opts['id'])) {
            $errors[] = "not specify IDs using both the short '-i' and long '--id' option syntax";
            return;
        }

        $idString = null;
        if(isset($opts['i'])) {
            $idString = $opts['i'];
        }
        elseif(isset($opts['id'])) {
            $idString = $opts['id'];
        }
        else {
            $errors[] = "specify IDs using either the short '-i' or long '--id' option syntax";
            return;
        }

        if(!is_string($idString)) {
            $errors[] = 'not specify the ID argument multiple times';
            return;
        }

        // Ensure it only contains integers and commas
        if('*' !== $idString && preg_match('/[^0-9,]/', $idString, $matches)) {
            $errors[] = "only use integers and commas when specifying IDs - no spaces (or '*' for all)";
            return;
        }

        $this->rootIds = explode(',', $idString);
    }


    /**
     * Determine whether command line args indicate we should perform the actions or just do a dry-run
     *
     * @param array $opts
     */
    private function getCommandLinePerform(array $opts)
    {
        $this->perform = isset($opts['p']) || isset($opts['perform']);
    }


    /**
     * @param       $errors
     * @param array $opts
     */
    private function getCommandLineMapperClass(&$errors, array $opts)
    {

        // Did user provide both short and long options?
        if(isset($opts['m'], $opts['mapper'])) {
            $errors[] = "specify mapper using either the short '-m' or long '--mapper' option syntax, but not both";
            return;
        }
        // Did user provide neither short nor long option
        if(!isset($opts['m']) && !isset($opts['mapper'])) {
            $errors[] = "specify mapper using either the short '-m' or long '--mapper' option syntax";

            return;
        }

        // What mapper are we targeting?
        $shortMapper = isset($opts['m']) ? $opts['m'] : null;
        $longMapper  = isset($opts['mapper']) ? $opts['mapper'] : null;

        if(is_string($shortMapper)) {
            $this->rootMapperClass = $shortMapper;
        }
        elseif(is_string($longMapper)) {
            $this->rootMapperClass = $longMapper;
        }
        else {
            $errors[] = 'not specify the mapper argument multiple times';
            return;
        }
    }


    /**
     * Determine the log verbosity from command line args
     *
     * @param array $opts
     */
    private function getCommandLineThreshold(array $opts)
    {
        $verboseOn  = 100; // max verbosity: EV-ER-Y-THING...
        $verboseOff = 200; // mod verbosity: most things

        $this->logThreshold = isset($opts['v']) || isset($opts['verbose']) ? $verboseOn : $verboseOff;
    }


    /**
     * Ensure the soft-delete registry contains the specified mapper
     *
     * @param string $mapperClass
     */
    private function checkRegistry($mapperClass)
    {
        // check this mapper is in our registry
        if(!array_key_exists($mapperClass, self::$softDeleteRegistry)) {
            $this->logger->error('Mapper is not registered in soft-delete registry. Unable to un-delete children');
            exit(1);
        }
        $this->logger->debug(sprintf("Mapper '%s' found in registry", $mapperClass));

        $childMappers = self::$softDeleteRegistry[$mapperClass];

        // check this mapper is defined properly in our registry
        if(!is_array($childMappers)) {
            $this->logger->error('Mapper is registered in soft-delete registry, but children not defined. Unable to un-delete children');
            exit(1);
        }
        $this->logger->debug(sprintf("Mapper '%s' has child mappers defined in registry", $mapperClass));
    }


    /**
     * @param string  $key
     * @param IMapper $mapper
     * @return string
     */
    private function getJoinColumn($key, IMapper $mapper)
    {
        if(is_string($key)) {
            return $key;
        }

        return "{$mapper->getTable()}_{$mapper->getIdentifierColumn()}";
    }


    /**
     * @param IMapper $childMapper
     * @param string  $joinCol
     * @param int[]   $parentIds
     * @return GenericCollection|ISoftDeletable[]
     */
    private function findDeletedChildModels(IMapper $childMapper, $joinCol, $parentIds)
    {
        $deletedChildModels = $childMapper
            ->findByColumn($joinCol, $parentIds, ['deleted' => true])
            ->filter(function (ISoftDeletable $model) {
                return $model->isDeleted();
            });
        $this->logger->info(sprintf('%d deleted child models found', $deletedChildModels->count()));

        return $deletedChildModels;
    }


    /**
     * Is a mapper listed in our registry has having child classes, but it does not have an identifier defined?
     *
     * This could occur if we didn't define a column as being an identifier in the mapper's mapping().
     *
     * @param string $childMapperClass
     */
    private function checkNoIdentifier($childMapperClass)
    {
        $this->logger->debug(sprintf("Child mapper '%s' does not appear to have an identifier. Checking registry", $childMapperClass));
        $this->checkRegistry($childMapperClass);

        // Ensure registry defines no child classes
        if(count(self::$softDeleteRegistry[$childMapperClass])) {
            $this->logger->error(sprintf("Mapper '%s' does not have identifier, but has child mappers registered", $childMapperClass));
            exit(1);
        }
        $this->logger->debug(sprintf("Mapper '%s' does not have identifier, but declares it has 0 child mappers", $childMapperClass));
    }


    /**
     * Recursive driver
     *
     * @param IMapper $mapper
     * @param int[]   $ids
     * @throws Mapper\Exception
     */
    private function findDeletedModels(IMapper $mapper, array $ids)
    {
        if(!$mapper->getIdentifierColumn()) {
            $this->logger->error("Mapper '%s' does not appear to have an identifier column. Unable to fetch root models", get_class($mapper));
            error(1);
        }

        // Restore roots
        $rootModels = $mapper->findByColumn($mapper->getIdentifierColumn(), $ids, ['deleted' => true]);

        $firstModel = $rootModels->first();
        if($firstModel && !$firstModel instanceof ISoftDeletable) {
            $this->logger->error("Mapper '%s' does not appear to be soft-deletable. Unable to restore", get_class($mapper));
            error(1);
        }

        $deletedRootModels = $rootModels->filter(function (ISoftDeletable $model) {
            return $model->isDeleted();
        });

        $this->registerDeletedModels($mapper, $deletedRootModels);

        // Enter recursive rabbit hole
        $this->recursiveFindDeletedModels($mapper, $deletedRootModels->getColumnValues($mapper->getIdentifierColumn()));
    }


    /**
     * Restores the soft-deleted models we found earlier
     */
    private function restoreSoftDeletedModels()
    {
        /**  @var IMapper $mapperClass */
        foreach($this->softDeletedModels as $mapperClass => $models) {
            $mapper      = $mapperClass::getInstance();
            $countModels = $models->count();
            if(0 === $countModels) {
                $this->logger->debug(sprintf("No soft-deleted models found for '%s', skipping...", $mapperClass));
                continue;
            }
            $this->logger->info(sprintf("Restoring %d soft-deleted models for '%s'...", $models->count(), $mapperClass));
            $mapper->restoreSoftDeleted($models);
        }
    }


    /**
     * Print the results with nicely-aligned columns
     *
     * @param object[][]|Collection[] $softDeletedModels
     */
    private function printResults(array $softDeletedModels)
    {
        $deDuplicatedResults = $this->buildDeDuplicatedResults($softDeletedModels);

        // How wide do columns need to be?
        $maxCountLength       = strlen('COUNT');
        $maxTableLength       = strlen('TABLE');
        $maxMapperClassLength = strlen('MAPPER');
        $maxIdsStringLength   = strlen('IDS');

        // Need to iterate first to find widest value in each column
        /** @var IMapper $mapperClass */
        foreach($deDuplicatedResults as $mapperClass => $models) {
            $count            = (string)count($models);
            $table            = $mapperClass::getInstance()->getTable();
            $rTrimMapperClass = preg_replace('/Mapper$/i', '', $mapperClass);
            $ids              = $this->getIdString($models->keys());

            $maxCountLength       = max($maxCountLength, strlen($count));
            $maxTableLength       = max($maxTableLength, strlen($table));
            $maxMapperClassLength = max($maxMapperClassLength, strlen($rTrimMapperClass));
            $maxIdsStringLength   = max($maxIdsStringLength, strlen($ids));
        }

        $reportWidth = 5 + $maxCountLength + $maxTableLength + $maxMapperClassLength + $maxIdsStringLength;

        $runType       = $this->perform ? 'LIVE RUN' : 'DRY RUN';
        $runTypeLength = strlen($runType);

        $countLeadingHyphens  = (int)(($reportWidth - $runTypeLength - 4) / 2);
        $countTrailingHyphens = $reportWidth - $runTypeLength - 4 - $countLeadingHyphens;
        $leadingHyphens       = str_repeat('-', $countLeadingHyphens);
        $trailingHyphens      = str_repeat('-', $countTrailingHyphens);

        $report = "\n$leadingHyphens Results - $runType $trailingHyphens\n";

        // Print table header
        $report .= sprintf("| %{$maxCountLength}s | %-{$maxTableLength}s | %-{$maxMapperClassLength}s | %-{$maxIdsStringLength}s |\n",
            'COUNT', 'TABLE', 'MAPPER', 'IDS');

        // Print table rows
        foreach($deDuplicatedResults as $mapperClass => $models) {
            $count            = (string)count($models);
            $table            = $mapperClass::getInstance()
                                            ->getTable();
            $rTrimMapperClass = preg_replace('/(\\\)?Mapper$/i', '', $mapperClass);
            $ids              = $this->getIdString($models->keys());

            $report .= sprintf("| %{$maxCountLength}s | %-{$maxTableLength}s | %-{$maxMapperClassLength}s | %-{$maxIdsStringLength}s |\n",
                $count, $table, $rTrimMapperClass, $ids);
        }

        $this->logger->info($report);
    }


    /**
     * It's theoretically possible for our graph to be acyclic, meaning the one logical entity may be present as
     * multiple models.
     *
     * For tables without a ID, we will need to dummy up our own for reporting and de-duplication
     *
     * @param array $softDeletedModels
     * @return object[][]|Collection[]
     */
    private function buildDeDuplicatedResults(array $softDeletedModels)
    {
        $deDuplicatedResults = [];
        /** @var IMapper $mapperClass */
        foreach($softDeletedModels as $mapperClass => $models) {
            $deDuplicatedResults[$mapperClass] = new Collection();
            $mapper                            = $mapperClass::getInstance();
            foreach($models as $model) {
                if($mapper->getIdentifierColumn()) {
                    $id = $mapper->getModelProperty($model, $mapper->getIdentifierProperty());
                }
                else {
                    $mapping = $mapper->getMap();
                    $id      = '';
                    $first   = true;
                    foreach($mapping as $map) {
                        if(!isset($map['property'])) {
                            continue;
                        }
                        // Ignore soft-delete fields
                        if(false !== strpos(strtolower($map['property']), 'deleted')) {
                            continue;
                        }
                        $property = $map['property'];
                        if(!$first) {
                            $id .= '_';
                        }
                        $id    .= $mapper->getModelProperty($model, $property);
                        $first = false;
                    }
                }
                $deDuplicatedResults[$mapperClass][$id] = $model;
            }
        }

        return $deDuplicatedResults;
    }


    /**
     * Recursive method to find soft-deleted models
     *
     * @param IMapper $mapper
     * @param int[]   $ids
     */
    private function recursiveFindDeletedModels(IMapper $mapper, array $ids)
    {
        $mapperClass = get_class($mapper);
        $this->checkRegistry($mapperClass);

        $childMappers = self::$softDeleteRegistry[$mapperClass];
        $this->logger->info(sprintf("Finding children of mapper '%s' with ids %s", $mapperClass, $this->getIdString($ids)));


        foreach($childMappers as $key => $childMapperClass) {
            $this->logger->info(sprintf("Fetching children from mapper '%s'", $childMapperClass));
            $childMapper        = $childMapperClass::getInstance();
            $joinCol            = $this->getJoinColumn($key, $mapper);
            $deletedChildModels = $this->findDeletedChildModels($childMapper, $joinCol, $ids);

            // Add these models to our results
            $this->registerDeletedModels($childMapper, $deletedChildModels);

            // Does child model have primary key identifier? (weak entities like folderCode do not)
            $childModelIdentifier = $childMapper->getIdentifierColumn();
            if(!$childModelIdentifier) {
                $this->checkNoIdentifier($childMapperClass);
                $this->logger->info(sprintf("Mapper '%s' does not have an identifier, therefore cannot have children", $childMapperClass));
                continue;
            }

            $childModelIds = $deletedChildModels->getColumnValues($childModelIdentifier);
            $this->logger->debug(sprintf('Child model IDs: %s', $this->getIdString($childModelIds)));

            if(0 === count($childModelIds)) {
                $this->logger->debug('No children found, skipping...');
                continue;
            }

            // Recurse!!
            $this->recursiveFindDeletedModels($childMapper, $childModelIds);
        }
    }


    /**
     * Adds the models to our results, for later processing.
     * Models are grouped by mapper, BUT NOT INDEXED TO ACCOUNT FOR MODELS WITHOUT IDENTIFIER COLUMNS
     *
     * This means we can get duplicate logical entities if our model graph is acyclic.
     * We'll have to de-dupe later for reporting.
     *
     * @param IMapper                            $mapper
     * @param GenericCollection|ISoftDeletable[] $deletedModels
     */
    private function registerDeletedModels(IMapper $mapper, GenericCollection $deletedModels)
    {
        $mapperClass = get_class($mapper);

        // Do we yet have results for this mapper ?
        if(!array_key_exists($mapperClass, $this->softDeletedModels)) {
            $this->softDeletedModels[$mapperClass] = new Collection;
        }

        $this->softDeletedModels[$mapperClass] = $this->softDeletedModels[$mapperClass]->merge($deletedModels);
    }


    /**
     * Return a single line string of IDs
     *
     * @param int[] $ids
     * @return string
     */
    private function getIdString(array $ids)
    {
        $string = '[';
        $first  = true;
        foreach($ids as $id) {
            if(!$first) {
                $string .= ',';
            }
            $string .= $id;
            $first  = false;
        }
        $string .= ']';

        return $string;
    }
}

