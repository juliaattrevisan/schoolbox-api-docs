#!/usr/bin/php
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
 * Usage: php ./RecursiveUndeleter.php -m{mapperClass} -i{ids} [-w{seconds}] [-v] [-p]
 *        php ./RecursiveUndeleter.php --mapper {mapperClass} --ids {ids} --window {seconds} [--verbose] [--perform]
 *
 *  ShortOpt | LongOpt
 *  -p       | --perform   *without* this flag, un-delete will only perform a dry-run
 *  -m       | --mapper    which mapper class does the root entity belong to?
 *  -i       | --id        Comma-separated list of IDs to un-delete, eg '1' or '1,2,3'. Use '*' to get all.
 *  -w       | --window    duration in seconds in which to match deletedAt dates. Children deleted outside span will not be restored.
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
use \Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile as ReturnedWorkFile;
use Schoolbox\Resource\File;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;


require_once __DIR__ . '/../library/Bootstrap.php';


$undeleter = new RecursiveUndeleter();
$undeleter->run();

class RecursiveUndeleter
{
    const COLOUR_GREEN = "\033[0;32m";
    const COLOUR_RED = "\033[0;31m";
    const COLOUR_GREY = "\033[0;37m";
    const COLOUR_YELLOW = "\033[0;33m";
    const COLOUR_WHITE = "\033[0m";

    const COLOUR_BOLD_GREEN = "\033[0;32m";
    const COLOUR_BOLD_RED = "\033[0;31m";
    const COLOUR_BOLD_GREY = "\033[0;37m";
    const COLOUR_BOLD_YELLOW = "\033[0;33m";

    // To account for legacy non-synchronised deletedAt timestamps, how much allowance should we consider as being
    // part of same action?
    const DEFAULT_DELETED_AT_WINDOW = 120 ; // default to 1 minute either side of root object's soft-delete timestamp

    /**
     * Period in seconds, centered on parent deletedAt timestamp, that child models must be deleted within
     * to be considered part of the same deletion action.
     *
     * @var int
     */
    private $deletedAtWindow;

    /**
     * If false, identify soft-deleted items and print report, but don't actually restore anything
     *
     * @var bool
     */
    private $perform;

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

    /** @var string */
    private $rootTable;

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
     * If non-standard naming (eg assess_*), or if joined via join table, then list the join column (string) as value, keyed by mapperClass
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
            Folder\Mapper::class => 'parent',   // yes, recursive tables are supported! :)
            FolderCode\Mapper::class,
            CourseSpec\Mapper::class,
            UnitSpec\Mapper::class,
            CourseImpl\Mapper::class,
            File\Mapper::class => 'category',
            LinkMapper::class  => 'category',
            Assessment\Mapper::class => 'v_f.folder_id'
        ],
        FolderCode\Mapper::class        => [],
        CourseSpec\Mapper::class        => [
            UnitSpec\Mapper::class,
        ],
        UnitSpec\Mapper::class          => [
            Assessment\Mapper::class => 'v_us.course_unit_specification_id',
        ],
        CourseImpl\Mapper::class        => [
            UnitImpl\Mapper::class,
        ],
        File\Mapper::class              => [],
        LinkMapper::class               => [],
        Assessment\Mapper::class        => [
            Quiz\Mapper::class,
            SubmissionBoxMapper::class,
            TaskMapper::class,
            Project\Mapper::class,
        ],
        UnitImpl\Mapper::class          => [
            Assessment\Mapper::class => 'v_ui.course_unit_implementation_id',
        ],
        Quiz\Mapper::class              => [
            QuizSection\Mapper::class => 'assess_section.assess_id',
            QuizAttempt\Mapper::class => 'assess_attempt.assess_id',
        ],
        SubmissionBoxMapper::class      => [
            SubmissionMapper::class   => 'student_submission.box_id',
            ReturnedWorkMapper::class => 'submission_return.box_id',
            Rubric\LevelMapper::class => 'rubric_level.box_id',
        ],
        TaskMapper::class               => [
            Task\CompletionMapper::class,
        ],
        Task\CompletionMapper::class    => [],
        Project\Mapper::class           => [
            Assessment\Mapper::class => 'v_pa.project_id',
        ],
        QuizSection\Mapper::class       => [
            QuizQuestion\Mapper::class => 'assess_question.section_id',
        ],
        QuizAttempt\Mapper::class       => [
            QuizAnswer\Mapper::class => 'assess_response.attempt_id',
        ],
        SubmissionMapper::class         => [
            SubmissionBoxFile\Mapper::class,
        ],
        ReturnedWorkMapper::class       => [
            ReturnedWorkFile\Mapper::class,
        ],
        Rubric\LevelMapper::class       => [
            Rubric\DescriptorMapper::class => 'rubric_descriptor.level_id',
        ],
        QuizQuestion\Mapper::class      => [
            QuizSolution\Mapper::class => 'assess_answer.question_id',
        ],
        QuizAnswer\Mapper::class        => [],
        QuizSolution\Mapper::class      => [],
        SubmissionBoxFile\Mapper::class => [
            Plagscan\Mapper::class,
        ],
        ReturnedWorkFile\Mapper::class  => [],
        Rubric\DescriptorMapper::class  => [],
        Plagscan\Mapper::class          => [],
        Rubric\CriterionMapper::class   => [
            Rubric\DescriptorMapper::class => 'rubric_descriptor.criterion_id',
        ]
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
            $this->connectToDatabase();
            $this->checkRestoreAllRequested();
            $this->findDeletedModels($this->rootMapper, $this->rootIds);
            $this->printResults($this->softDeletedModels);
            $this->printDeletedAtDates($this->softDeletedModels);

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

    private function connectToDatabase()
    {
        $this->database = Database::getInstance();
    }

    /**
     * If the user specified all IDs ('*') on command line, load all ids for the specified mapper.
     *
     * Log error and exit if specified mapper does not have an ID field registered.
     */
    private function checkRestoreAllRequested()
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
        // Use the specified root table to find the root mapper
        /** @var IMapper $mapperClass */
        foreach(self::$softDeleteRegistry as $mapperClass => $children){

            // A bit of house keeping to try to keep things in order
            if(!class_exists($mapperClass)) {
                $this->logger->warning("Registry corruption detected. Mapper class does not exist: {$mapperClass}");
            }

            try {
                $mapperInstance = $mapperClass::getInstance();
                if($mapperInstance->getTable() === $this->rootTable){
                    $this->rootMapper = $mapperInstance;
                    return;
                }
            }
            catch(Exception $e) {
                $this->logger->warning($e->getMessage());
            }
        }

        $this->logger->error("No mapper found in registry with table: '{$this->rootTable}'");
        exit(1);
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
        $opts   = getopt('t:i:o:w:vp', ['table:', 'id:', 'override:', 'window:', 'verbose', 'perform']);
        $errors = [];
        $this->getCommandLinePerform($opts);
        $this->getCommandLineThreshold($opts);
        $errors = $this->getCommandLineExplicitDeletionDate($errors, $opts);
        $errors = $this->getCommandLineTable($errors, $opts);
        $errors = $this->getCommandLineIds($errors, $opts);
        $errors = $this->getCommandLineTimestampWindow($errors, $opts);

        $red    = self::COLOUR_RED;
        $green  = self::COLOUR_GREEN;
        $white  = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;

        $dateFormat = Alaress_Date_Format::DATABASE_FORMAT;

        if(count($errors)) {
            echo "\n{$red}Invalid syntax. You must: \n";
            foreach($errors as $error) {
                echo "\t- $error\n";
            }

            $file   = __FILE__;
            $class  = __CLASS__;
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
              comma-separated list of IDs to un-delete, eg '1' or '1,2,3'. Use '*' to get all.
              If any entity with a specified ID is not soft-deleted, it will be ignored.

       {$yellow}-t, --table=TABLE{$white}
              which database table does the root entity reside in?

       {$yellow}-p, --perform{$white}
              *without* this flag, un-delete will only perform a dry-run

       {$yellow}-o, --override=OVERRIDE_DATE{$white}
              Format: {$yellow}'{$dateFormat}'{$white}
              If specified, treat specified root objects as having been deleted at this date, regardless of when they
              were really deleted, whether they have a boolean soft-delete field, or whether they are deleted at all.
              Note, this does NOT mean restore root objects deleted around this time!

       {$yellow}-w, --window=WINDOW{$white}
              duration in seconds in which to match deletedAt dates. Children deleted outside span will not be restored.

       {$yellow}-v, --verbose{$white}
              verbosity:   whether to print verbose output or not.

EXAMPLES:
       1. To undelete a soft-deleted folder with ID = 123:
            {$green}$file {$yellow}-t '$table' -i 123 -p{$white}

       2. To undelete children of a non-soft-deleted folder with ID = 123. Restore children deleted within
          one hour before or after '2000-01-01 00:00:00':
            {$green}$file {$yellow}-t '$table' -i 123 -o '2000-01-01 00:00:00' -w 7200 -p{$white}

";
            echo $helpMsg;
            exit(1);
        }
    }


    /**
     * Get the IDs provided on command line
     *
     * @param string[] $errors
     * @param array    $opts
     * @return string[]
     */
    private function getCommandLineIds(array $errors, array $opts)
    {
        $red    = self::COLOUR_RED;
        $yellow = self::COLOUR_YELLOW;

        if(isset($opts['i'], $opts['id'])) {
            $errors[] = "{$red}not specify IDs using both the short '{$yellow}-i{$red}' and long '{$yellow}--id{$red}' option syntax";
            return $errors;
        }

        $idString = null;
        if(isset($opts['i'])) {
            $idString = $opts['i'];
        }
        elseif(isset($opts['id'])) {
            $idString = $opts['id'];
        }
        else {
            $errors[] = "{$red}specify IDs using either the short '{$yellow}-i{$red}' or long '{$yellow}--id{$red}' option syntax";
            return $errors;
        }

        if(!is_string($idString)) {
            $errors[] = 'not specify the ID argument multiple times';
            return $errors;
        }

        // Ensure it only contains integers and commas
        if('*' !== $idString && preg_match('/[^0-9,]/', $idString, $matches)) {
            $errors[] = "only use integers and commas when specifying IDs - no spaces (or '*' for all)";
            return $errors;
        }

        $this->rootIds = explode(',', $idString);
        return $errors;
    }

    /**
     * Get the period in time in which to match deleted_at timestamps provided on command line
     *
     * @param string[] $errors
     * @param array    $opts
     * @return string[]
     */
    private function getCommandLineTimestampWindow(array $errors, array $opts)
    {
        $red    = self::COLOUR_RED;
        $yellow = self::COLOUR_YELLOW;

        if(isset($opts['w'], $opts['window'])) {
            $errors[] = "{$red}not specify timestamp window using both the short '{$yellow}-w{$red}' and long '{$yellow}--window{$red}' option syntax";
            return $errors;
        }

        $window = null;
        if(isset($opts['w'])) {
            $window = $opts['w'];
        }
        elseif(isset($opts['window'])) {
            $window = $opts['window'];
        }

        if(null !== $window && preg_match('/\D+/', $window)){
            $errors[] = 'use only integers (seconds) when specifying the deletedAt timestamp window width, eg \'120\'';
            return $errors;
        }
        if(null === $window){
            $this->deletedAtWindow = self::DEFAULT_DELETED_AT_WINDOW;
        } else {
            $this->deletedAtWindow = (int)$window;
        }
        return $errors;
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
     * @param string[] $errors
     * @param array $opts
     * @return string[]
     */
    private function getCommandLineTable(array $errors, array $opts)
    {
        $red    = self::COLOUR_RED;
        $yellow = self::COLOUR_YELLOW;

        // Did user provide both short and long options?
        if(isset($opts['t'], $opts['table'])) {
            $errors[] = "{$red}specify database table using either the short '{$yellow}-t{$red}' or long '{$yellow}--table{$red}' option syntax, but not both";
            return $errors;
        }
        // Did user provide neither short nor long option
        if(!isset($opts['t']) && !isset($opts['table'])) {
            $errors[] = "{$red}specify database table using either the short '{$yellow}-t{$red}' or long '{$yellow}--table{$red}' option syntax";
            return $errors;
        }

        // What table are we targeting?
        $shortOpt = isset($opts['t']) ? $opts['t'] : null;
        $longOpt  = isset($opts['table']) ? $opts['table'] : null;

        if(is_string($shortOpt)) {
            $this->rootTable = $shortOpt;
        }
        elseif(is_string($longOpt)) {
            $this->rootTable = $longOpt;
        }
        else {
            $errors[] = 'not specify the database table option multiple times';
        }
        return $errors;
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
     * Did caller specify to
     *
     * @param string[] $errors
     * @param array    $opts
     * @return string[]
     */
    private function getCommandLineExplicitDeletionDate(array $errors, array $opts)
    {
        $red    = self::COLOUR_RED;
        $yellow = self::COLOUR_YELLOW;

        // Did user provide both short and long options?
        if (isset($opts['o'], $opts['override'])) {
            $errors[] = "{$red}specify override deletion date using either the short '{$yellow}-o{$red}' or long '{$yellow}--override{$red}' option syntax, but not both";
            return $errors;
        }

        // Did user specify neither?
        if (!isset($opts['o']) && !isset($opts['override'])) {
            return $errors;
        }

        $dateString = isset($opts['o']) ? $opts['o'] : $opts['override'];

        try {
            $this->explicitDeletionDate = new Alaress_Date_Format($dateString, Alaress_Date_Format::DATABASE_FORMAT);
        } catch (Exception $e) {
            echo self::COLOUR_RED."Could not generate a date from string: {$dateString}\n";
            echo "{$e->getMessage()}\n".self::COLOUR_WHITE;
            exit(1);
        }

        return $errors;
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
     * Parse the registered key+val to derive a Mapper instance and foreign key
     *
     * @param IMapper $parentMapper
     * @param string  $key
     * @param string  $val
     * @return mixed[]
     */
    private function getMapperClassAndForeignKeyFromRegistryPair(IMapper $parentMapper, $key, $val)
    {
        if(is_string($key)) {
            /** @var IMapper $key */
            $childMapper = $key::getInstance();
            $foreignKey = $val;
        } else {
            /** @var IMapper $val */
            $childMapper = $val::getInstance();
            $foreignKey = "{$parentMapper->getTable()}_{$parentMapper->getIdentifierColumn()}";
        }
        return [$childMapper, $foreignKey];
    }


    /**
     * @param IMapper                  $childMapper
     * @param string                   $foreignKeyColumn
     * @param int[]                    $parentIds
     * @param null|Alaress_Date_Format $deletedAfter
     * @param null|Alaress_Date_Format $deletedBefore
     * @return GenericCollection|ISoftDeletable[]
     */
    private function findDeletedChildModels(
        IMapper $childMapper,
        $foreignKeyColumn,
        array $parentIds,
        Alaress_Date_Format $deletedAfter = null,
        Alaress_Date_Format $deletedBefore = null
    ){
        $childModels = $childMapper
            ->findByColumn($foreignKeyColumn, $parentIds, ['deleted' => true])
            ->filter(function (ISoftDeletable $model) {
                return $model->isDeleted();
            });

        // Do the child models have a timestamp (not boolean) soft-delete field, and we have dates to filter?
        if($deletedAfter && $deletedBefore && $childModels->first() && method_exists($childModels->first(), 'getDeletedAt')){
            $childModels = $childModels->filter(function($childModel) use ($deletedAfter, $deletedBefore){
                /** @var Mapper\Traits\Model\DeletedAtDate $childModel */
                $deletedAt = $childModel->getDeletedAt();
                return $deletedAt->between($deletedAfter, $deletedBefore);
            });
        }
        $this->logger->info(sprintf('%d deleted child models found', $childModels->count()));
        return $childModels;
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
     * Each root model specified on command line can have different deletedAt timestamps
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
        if($firstModel && !$firstModel instanceof ISoftDeletable && !$this->explicitDeletionDate) {
            $this->logger->error("Mapper '%s' does not appear to be soft-deletable, and no override deletion"
            . ' date specified. Unable to restore', get_class($mapper));
            error(1);
        }

        // Filter to just deleted root models
        /** @var GenericCollection $deletedRootModels */
        $deletedRootModels = $rootModels->filter(function (ISoftDeletable $model) {
            return $model->isDeleted();
        });

        // Add these to our 'found' set
        $this->registerDeletedModels($mapper, $deletedRootModels);

        /**
         * If an override deletion date was specified, traverse all root models, else only deleted root models
         *
         * @var object[] $rootModelsToTraverse
         */
        $rootModelsToTraverse = $this->explicitDeletionDate ? $rootModels : $deletedRootModels;

        // For each root model, recursively search for deleted children (matching the root's timestamp)
        foreach($rootModelsToTraverse as $rootModel){
            // Try to get deletedAt timespan
            list($deletedAfter, $deletedBefore) = $this->getDeletedBetweenDates($rootModel);

            // Enter recursive rabbit hole
            $this->recursiveFindDeletedModels($mapper, $mapper->getModelAsCollection($rootModel), $deletedAfter, $deletedBefore);
        }
    }


    /**
     * If model has a timestamp (not boolean) soft-delete field, generate a pair of ADFs that child objects must
     * be deleted between to be considered part of the same delete cascade.
     *
     * If the object is not deleted, returns scalar NULLs
     *
     * @param object $model
     * @return null[]|Alaress_Date_Format[]
     */
    private function getDeletedBetweenDates($model)
    {
        $deletedAfter = $deletedBefore = null;
        if($this->explicitDeletionDate || method_exists($model, 'getDeletedAt')){
            /** @var Mapper\Traits\Model\DeletedAtDate $model */
            $deletedAt = $this->explicitDeletionDate ?: $model->getDeletedAt();
            $this->logger->info(
                sprintf("Restoring soft-deleted models deleted within %d seconds before and after '%s'...",
                    $this->deletedAtWindow / 2,
                    $deletedAt->getDatabase()
                )
            );
            if($deletedAt instanceof Alaress_Date_Format && !$deletedAt instanceof Alaress_Date_NullDate){
                $deletedAfter  = clone $deletedAt;
                $deletedAfter->addSeconds(-1 * $this->deletedAtWindow / 2);

                $deletedBefore = clone $deletedAt;
                $deletedBefore->addSeconds(+1 * $this->deletedAtWindow / 2);
            }
        }
        return [$deletedAfter, $deletedBefore];
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
                $this->logger->debug(
                    sprintf("No soft-deleted models found for table '%s' (mapper '%s'), skipping...",
                        $mapper->getTable(),
                        $mapperClass)
                );
                continue;
            }
            $this->logger->info(
                sprintf("Restoring %d soft-deleted models for table '%s' (mapper '%s')...",
                    $models->count(),
                    $mapper->getTable(),
                    $mapperClass)
            );
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
        $maxIdFieldWidth = 100; // with two space chars adjacent

        $deDuplicatedResults = $this->buildDeDuplicatedResults($softDeletedModels);

        // How wide do columns need to be?
        $maxCountLength       = strlen('COUNT');
        $maxTableLength       = strlen('TABLE');
        $maxMapperClassLength = strlen('MAPPER');
        $maxIdsStringLength   = min($maxIdFieldWidth, strlen('IDS'));

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
            $maxIdsStringLength   = max($maxIdsStringLength, min($maxIdFieldWidth, strlen($ids)));
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

            // Print the first 3 non-wrapping columns
            $report .= sprintf("| %{$maxCountLength}s | %-{$maxTableLength}s | %-{$maxMapperClassLength}s | [",
                $count, $table, $rTrimMapperClass);

            $countLineCharsAvailable = $maxIdsStringLength - 1;

            $countKeys = count($models->keys());
            foreach($models->keys() as $idx => $id){
                $isLast = $countKeys === ($idx + 1);

                $idString = $isLast ? "{$id}" : "{$id},";

                // Do we have room to print this id on this line?
                $idLength = strlen($idString);
                if($countLineCharsAvailable - $idLength < 0){
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
     * @param object[][]|Collection[] $softDeletedModels
     */
    private function printDeletedAtDates(array $softDeletedModels)
    {
        $deDuplicatedResults = $this->buildDeDuplicatedResults($softDeletedModels);

        /** @var IMapper $mapperClass */
        $firstLoop = true;
        foreach($deDuplicatedResults as $mapperClass => $models) {
            $firstModel = $models->first();

            // No models, or does not have a 'getDeletedAt()' method?
            if(!$firstModel || !method_exists($firstModel, 'getDeletedAt')){
                continue;
            }
            $mapper = $mapperClass::getInstance();

            if(!$firstLoop){
                $this->logger->info('');
            }
            $this->logger->info(sprintf("Deleted at values for table '%s' (mapper '%s'):", $mapper->getTable(), $mapperClass));

            // Align ID columns. Right-align numeric, left align textual.
            $longestId = 0;
            $rightAlign = true;
            foreach($models as $model){
                $id = $this->buildId($mapper, $model);
                $longestId = max($longestId, strlen($id));
                if((string)(int) $id !== $id){
                    $rightAlign = false;
                }
            }

            $alignment = $rightAlign ? '' : '-';
            foreach($models as $model){
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
     * @param         $model
     * @return string
     */
    private function buildId(IMapper $mapper, $model)
    {
        // Does this model have an identifier?
        if($mapper->getIdentifierColumn()) {
            return (string)$mapper->getModelProperty($model, $mapper->getIdentifierProperty());
        }

        // No identifier, build our own using all but the 'deletedAt' field.
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
        return $id;

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
                $id = $this->buildId($mapper, $model);
                $deDuplicatedResults[$mapperClass][$id] = $model;
            }
        }

        return $deDuplicatedResults;
    }


    /**
     * Recursive method to find soft-deleted models
     *
     * If $after and $before are not null, and child models have timestamp soft-delete field, filter to between dates
     *
     * @param IMapper                  $parentMapper
     * @param Collection               $deletedParentModels
     * @param null|Alaress_Date_Format $after
     * @param null|Alaress_Date_Format $before
     */
    private function recursiveFindDeletedModels(
        IMapper $parentMapper,
        Collection $deletedParentModels,
        Alaress_Date_Format $after = null,
        Alaress_Date_Format $before = null
    ){
        $mapperClass = get_class($parentMapper);
        $this->checkRegistry($mapperClass);

        $childMappers = self::$softDeleteRegistry[$mapperClass];
        $parentIds    = $deletedParentModels->getPropertyValues($parentMapper->getIdentifierProperty());
        $this->logger->info(sprintf("Finding children of mapper '%s' with ids %s", $mapperClass, $this->getIdString($parentIds)));

        // For each child entity of this parent entity
        foreach($childMappers as $key => $val) {
            /**
             * @var IMapper $childMapper
             * @var string  $foreignKeyColumn
             */
            list($childMapper, $foreignKeyColumn) = $this->getMapperClassAndForeignKeyFromRegistryPair($parentMapper, $key, $val);
            $mapperClass = get_class($childMapper);
            $this->logger->info(sprintf("Fetching children from mapper '%s'", $mapperClass));

            $deletedChildModels = $this->findDeletedChildModels($childMapper, $foreignKeyColumn, $parentIds, $after, $before);

            // Add these models to our results
            $this->registerDeletedModels($childMapper, $deletedChildModels);

            // Does child model have primary key identifier? (weak entities like folderCode do not)
            $childModelIdentifier = $childMapper->getIdentifierColumn();
            if(!$childModelIdentifier) {
                $this->checkNoIdentifier($val);
                $this->logger->info(sprintf("Mapper '%s' does not have an identifier, therefore cannot have children", $val));
                continue;
            }

            $this->logger->info(sprintf("Child mapper identifier column '%s'", $childModelIdentifier));
            $childModelIds = $deletedChildModels->getPropertyValues($childMapper->getIdentifierProperty());
            $this->logger->debug(sprintf('Child model IDs: %s', $this->getIdString($childModelIds)));

            if(0 === count($childModelIds)) {
                $this->logger->debug('No children found, skipping...');
                continue;
            }

            if(null === $after && null === $before){
                list($after, $before) = $this->getDeletedBetweenDates($deletedChildModels->first());
            }

            // Recurse!!
            $this->recursiveFindDeletedModels($childMapper, $deletedChildModels, $after, $before);
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

