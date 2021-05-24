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
 *  -i       | --id        Comma-separated list of IDs to un-delete, eg '1' or '1,2,3'. Use '*' to get all.
 *  -w       | --window    duration in seconds in which to match deletedAt dates. Children deleted outside span will not be restored.
 *  -v       | --verbose   verbosity:   how much activity will be logged.
 */

namespace Schoolbox\Mapper;

use Alaress\Lumberjack\Logger;
use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Component\Eportfolio as ComponentEportfolio;
use Eportfolio;
use UnexpectedValueException;
use OutOfBoundsException;
use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile as ReturnedWorkFile;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile as StudentSubmissionFile;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Assessment\Rubric\DataModel\Descriptor\Mapper as RubricDescriptorMapper;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as RubricIndicatorMapper;
use Schoolbox\Assessment\Rubric\DataModel\Level\Mapper as RubricLevelMapper;
use Schoolbox\Blog;
use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\Curriculum;
use Schoolbox\Discussion;
use Schoolbox\GenericCollection;
use Schoolbox\IMapper;
use Schoolbox\ISoftDeletable;
use Schoolbox\Fixtures;
use Schoolbox\Forum;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\DataModel\Project;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\Learning\Quiz;
use Schoolbox\Learning\Quiz\Attempt as QuizAttempt;
use Schoolbox\Learning\Quiz\Attempt\Answer as QuizAnswer;
use Schoolbox\Learning\Quiz\Section as QuizSection;
use Schoolbox\Learning\Quiz\Section\Question as QuizQuestion;
use Schoolbox\Learning\Quiz\Section\Question\Solution as QuizSolution;
use Schoolbox\Mapper;
use Schoolbox\Pastoral;
use Schoolbox\Resource\File;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Code as FolderCode;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\Resource\My\File as MyFile;
use Schoolbox\ResourceBooking;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

use function count;
use function get_class;
use function method_exists;
use function sprintf;

class RecursiveUndeleter
{

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
            Assessment\Mapper::class => 'v_f.folder_id',
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
        Assessment\Mapper::class            => [
            Quiz\Mapper::class,
            SubmissionBoxMapper::class,
            TaskMapper::class,
            Project\Mapper::class,
        ],
        UnitImpl\Mapper::class              => [
            Assessment\Mapper::class => 'v_ui.course_unit_implementation_id',
        ],
        Quiz\Mapper::class                  => [
            QuizSection\Mapper::class => 'assess_id',
            QuizAttempt\Mapper::class => 'assess_id',
        ],
        SubmissionBoxMapper::class          => [
            SubmissionMapper::class   => 'box_id',
            ReturnedWorkMapper::class => 'box_id',
            RubricLevelMapper::class  => 'box_id',
            Eportfolio\ShowcaseMapper::class,
        ],
        TaskMapper::class                   => [
            Task\CompletionMapper::class,
        ],
        Task\CompletionMapper::class        => [],
        Project\Mapper::class               => [
            Assessment\Mapper::class => 'v_pa.project_id',
        ],
        QuizSection\Mapper::class           => [
            QuizQuestion\Mapper::class => 'section_id',
        ],
        QuizAttempt\Mapper::class           => [
            QuizAnswer\Mapper::class => 'attempt_id',
        ],
        SubmissionMapper::class             => [
            StudentSubmissionFile\Mapper::class,
        ],
        ReturnedWorkMapper::class           => [
            ReturnedWorkFile\Mapper::class,
        ],
        RubricLevelMapper::class            => [
            RubricDescriptorMapper::class => 'rubric_descriptor.level_id',
        ],
        Eportfolio\ShowcaseMapper::class    => [],
        QuizQuestion\Mapper::class          => [
            QuizSolution\Mapper::class => 'question_id',
        ],
        QuizAnswer\Mapper::class            => [],
        QuizSolution\Mapper::class          => [],
        StudentSubmissionFile\Mapper::class => [
            Plagscan\Mapper::class,
            Kami\Mapper::class,
        ],
        ReturnedWorkFile\Mapper::class      => [],
        RubricDescriptorMapper::class       => [],
        Plagscan\Mapper::class              => [],
        Kami\Mapper::class                  => [],
        RubricIndicatorMapper::class        => [
            RubricDescriptorMapper::class => 'rubric_descriptor.rubric_indicator_id',
        ],
        UserMapper::class                   => [
            Pastoral\Access\Mapper::class,
            Pastoral\Audience\Mapper::class,
        ],
        RoleMapper::class                   => [
            Pastoral\Access\Mapper::class => 'role_id',
            Pastoral\Audience\Mapper::class => 'role_id',
        ],
        Pastoral\Mapper::class          => [
            Pastoral\Comment\Mapper::class,
            Pastoral\Attachment\Mapper::class,
            Pastoral\Access\Mapper::class,
        ],
        Pastoral\Group\Mapper::class        => [
            Pastoral\Access\Mapper::class => 'group_id',
            Pastoral\Audience\Mapper::class => 'group_id',
            Pastoral\Group\Member\Mapper::class => 'group_id',
        ],
        Pastoral\Type\Mapper::class         => [
            Pastoral\Audience\Mapper::class => 'type_id',
        ],
        Pastoral\SubType\Mapper::class      => [
            Pastoral\Audience\Mapper::class => 'subtype_id',
        ],
        Pastoral\Access\Mapper::class       => [],
        Pastoral\Audience\Mapper::class     => [],
        Pastoral\Group\Member\Mapper::class => [],
        Discussion\Thread\Mapper::class     => [
            Discussion\Comment\Mapper::class => 'discussion_thread_id',
        ],
        Discussion\Comment\Mapper::class    => [],
        Eportfolio\PageMapper::class        => [],
        Blog\Homepage\Post\Mapper::class                => [
            Blog\Homepage\Comment\Mapper::class,
            Blog\Homepage\Image\Mapper::class,
        ],
        Blog\Homepage\Comment\Mapper::class             => [],
        Blog\Homepage\Image\Mapper::class               => [],
        Curriculum\Node\Mapper::class                   => [
            Curriculum\Node\Mapper::class => 'curriculum_node_parent_id',
        ],
        Fixtures\Division\Mapper::class                 => [
            Fixtures\Team\Mapper::class          => 'division_id',
            Fixtures\Division\User\Mapper::class => 'division_id',
        ],
        Fixtures\Division\User\Mapper::class            => [],
        Fixtures\Match\Mapper::class                    => [
            Fixtures\ItineraryItem\Mapper::class => 'match_id',
            Fixtures\Result\Mapper::class        => 'match_id',
        ],
        Fixtures\ItineraryItem\Mapper::class            => [],
        Fixtures\Result\Mapper::class                   => [],
        Fixtures\Season\Mapper::class                   => [
            Fixtures\Division\Mapper::class => 'season_id',
            Fixtures\Match\Mapper::class    => 'season_id',
        ],
        Fixtures\Sport\Mapper::class                    => [
            Fixtures\Division\Mapper::class => 'sport_id',
            Fixtures\Position\Mapper::class => 'sport_id',
        ],
        Fixtures\Position\Mapper::class                 => [],
        Fixtures\Team\Mapper::class                     => [
            Fixtures\Team\Player\Mapper::class => 'team_id',
        ],
        Fixtures\Team\Player\Mapper::class              => [],
        Pastoral\Attachment\Mapper::class               => [
            Pastoral\UploadQueue\Mapper::class,
        ],
        Pastoral\UploadQueue\Mapper::class              => [],
        MyFile\Mapper::class                            => [
            ComponentEportfolio\FileListMapper::class => 'file_id',
        ],
        ComponentEportfolio\FileListMapper::class       => [],
        ResourceBooking\Item\Mapper::class              => [
            ResourceBooking\Booking\Mapper::class,
        ],
        ResourceBooking\Booking\Mapper::class           => [],
        Pastoral\Comment\Mapper::class                  => [],
        Forum\ThreadMapper::class                       => [
            Forum\PostMapper::class => 'threadID',
        ],
        Forum\PostMapper::class                         => [],
        News\Mapper::class                              => [],
    ];


    public function __construct(LoggerInterface $logger, $deletedAtWindow = null, $explicitDeletionDate = null)
    {
        $this->logger = $logger;
        $this->deletedAtWindow = $deletedAtWindow;
        $this->explicitDeletionDate = $explicitDeletionDate;
    }


    /**
     * Ensure the soft-delete registry contains the specified mapper
     *
     * @param string $mapperClass
     * @throws OutOfBoundsException
     */
    private function checkRegistry($mapperClass): void
    {
        // check this mapper is in our registry
        if (!array_key_exists($mapperClass, self::$softDeleteRegistry)) {
            $this->logger->error('Mapper is not registered in soft-delete registry. Unable to un-delete children');
            throw new OutOfBoundsException('Mapper is not registered in soft-delete registry. Unable to un-delete children');
        }
        $this->logger->debug(sprintf("Mapper '%s' found in registry", $mapperClass));

        $childMappers = self::$softDeleteRegistry[$mapperClass];

        // check this mapper is defined properly in our registry
        if (!is_array($childMappers)) {
            $this->logger->error('Mapper is registered in soft-delete registry, but children not defined. Unable to un-delete children');
            throw new OutOfBoundsException('Mapper is registered in soft-delete registry, but children not defined. Unable to un-delete children');
        }
        $this->logger->debug(sprintf("Mapper '%s' has child mappers defined in registry", $mapperClass));
    }

    /**
     * Load an instance of the root mapper from the table name
     * @param string $table
     * @throws UnexpectedValueException
     * @return IMapper
     */
    public function loadMapper(string $table): IMapper
    {
        // Use the specified root table to find the root mapper
        /** @var IMapper $mapperClass */
        foreach (self::$softDeleteRegistry as $mapperClass => $children) {
            // A bit of house keeping to try to keep things in order
            if (!class_exists($mapperClass)) {
                $this->logger->warning("Registry corruption detected. Mapper class does not exist: {$mapperClass}");
            }

            try {
                $mapperInstance = $mapperClass::getInstance();
                if ($mapperInstance->getTable() === $table) {
                    return $mapperInstance;
                }
            } catch (\Exception $e) {
                $this->logger->warning($e->getMessage());
            }
        }

        $this->logger->error("No mapper found in registry with table: '{$table}'");
        throw new UnexpectedValueException("No mapper found in registry with table: '{$table}'");
    }


    /**
     * Parse the registered key+val to derive a Mapper instance and foreign key
     *
     * @param IMapper $parentMapper
     * @param string  $key
     * @param string  $val
     * @return mixed[]
     */
    private function getMapperClassAndForeignKeyFromRegistryPair(IMapper $parentMapper, $key, $val): array
    {
        if (is_string($key)) {
            /** @var IMapper $key */
            $childMapper = $key::getInstance();
            $foreignKey  = $val;
        } else {
            /** @var IMapper $val */
            $childMapper = $val::getInstance();
            $foreignKey  = "{$parentMapper->getTable()}_{$parentMapper->getIdentifierColumn()}";
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
    ) {
        $childModels = $childMapper
            ->findByColumn($foreignKeyColumn, $parentIds, ['deleted' => true])
            ->filter(function (ISoftDeletable $model) {
                return $model->isDeleted();
            });

        // Do the child models have a timestamp (not boolean) soft-delete field, and we have dates to filter?
        if ($deletedAfter && $deletedBefore && $childModels->first() && method_exists($childModels->first(), 'getDeletedAt')) {
            $childModels = $childModels->filter(function ($childModel) use ($deletedAfter, $deletedBefore) {
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
    private function checkNoIdentifier($childMapperClass): void
    {
        $this->logger->debug(sprintf("Child mapper '%s' does not appear to have an identifier. Checking registry", $childMapperClass));
        $this->checkRegistry($childMapperClass);

        // Ensure registry defines no child classes
        if (count(self::$softDeleteRegistry[$childMapperClass])) {
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
    public function findDeletedModels(IMapper $mapper, array $ids): array
    {
        if (!$mapper->getIdentifierColumn()) {
            $this->logger->error(sprintf("Mapper '%s' does not appear to have an identifier column. Unable to fetch root models", get_class($mapper)));
            error(1);
        }

        // Restore roots
        $rootModels = $mapper->findByColumn($mapper->getIdentifierColumn(), $ids, ['deleted' => true]);

        $firstModel = $rootModels->first();
        if ($firstModel && !$firstModel instanceof ISoftDeletable && !$this->explicitDeletionDate) {
            $this->logger->error(sprintf("Mapper '%s' does not appear to be soft-deletable, and no override deletion"
                . ' date specified. Unable to restore', get_class($mapper)));
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
        foreach ($rootModelsToTraverse as $rootModel) {
            // Try to get deletedAt timespan
            [$deletedAfter, $deletedBefore] = $this->getDeletedBetweenDates($rootModel);

            // Enter recursive rabbit hole
            $this->recursiveFindDeletedModels($mapper, $mapper->getModelAsCollection($rootModel), $deletedAfter, $deletedBefore);
        }

        return $this->softDeletedModels;
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
    private function getDeletedBetweenDates($model): array
    {
        $deletedAfter = $deletedBefore = null;
        if ($this->explicitDeletionDate || method_exists($model, 'getDeletedAt')) {
            $deletedAt = $this->explicitDeletionDate ?: $model->getDeletedAt();
            $this->logger->info(
                sprintf(
                    "Restoring soft-deleted models deleted within %d seconds before and after '%s'...",
                    $this->deletedAtWindow / 2,
                    $deletedAt->getDatabase()
                )
            );
            if ($deletedAt instanceof Alaress_Date_Format && !$deletedAt instanceof Alaress_Date_NullDate) {
                $deletedAfter = clone $deletedAt;
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
    public function restoreSoftDeletedModels(): void
    {
        /**  @var IMapper $mapperClass */
        foreach ($this->softDeletedModels as $mapperClass => $models) {
            $mapper      = $mapperClass::getInstance();
            $countModels = $models->count();
            if (0 === $countModels) {
                $this->logger->debug(
                    sprintf(
                        "No soft-deleted models found for table '%s' (mapper '%s'), skipping...",
                        $mapper->getTable(),
                        $mapperClass
                    )
                );
                continue;
            }
            $this->logger->info(
                sprintf(
                    "Restoring %d soft-deleted models for table '%s' (mapper '%s')...",
                    $models->count(),
                    $mapper->getTable(),
                    $mapperClass
                )
            );
            $mapper->restoreSoftDeleted($models);
        }
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
    ): void {
        $mapperClass = get_class($parentMapper);
        $this->checkRegistry($mapperClass);

        $childMappers = self::$softDeleteRegistry[$mapperClass];
        $parentIds    = $deletedParentModels->getPropertyValues($parentMapper->getIdentifierProperty());
        $this->logger->info(sprintf("Finding children of mapper '%s' with ids %s", $mapperClass, $this->getIdString($parentIds)));

        // For each child entity of this parent entity
        foreach ($childMappers as $key => $val) {
            /**
             * @var IMapper $childMapper
             * @var string  $foreignKeyColumn
             */
            [$childMapper, $foreignKeyColumn] = $this->getMapperClassAndForeignKeyFromRegistryPair($parentMapper, $key, $val);
            $mapperClass = get_class($childMapper);
            $this->logger->info(sprintf("Fetching children from mapper '%s'", $mapperClass));

            $deletedChildModels = $this->findDeletedChildModels($childMapper, $foreignKeyColumn, $parentIds, $after, $before);

            // Add these models to our results
            $this->registerDeletedModels($childMapper, $deletedChildModels);

            // Does child model have primary key identifier? (weak entities like folderCode do not)
            $childModelIdentifier = $childMapper->getIdentifierColumn();
            if (!$childModelIdentifier) {
                $this->checkNoIdentifier($val);
                $this->logger->info(sprintf("Mapper '%s' does not have an identifier, therefore cannot have children", $val));
                continue;
            }

            $this->logger->info(sprintf("Child mapper identifier column '%s'", $childModelIdentifier));
            $childModelIds = $deletedChildModels->getPropertyValues($childMapper->getIdentifierProperty());
            $this->logger->debug(sprintf('Child model IDs: %s', $this->getIdString($childModelIds)));

            if (0 === count($childModelIds)) {
                $this->logger->debug('No children found, skipping...');
                continue;
            }

            if (null === $after && null === $before) {
                [$after, $before] = $this->getDeletedBetweenDates($deletedChildModels->first());
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
    private function registerDeletedModels(IMapper $mapper, GenericCollection $deletedModels): void
    {
        $mapperClass = get_class($mapper);

        // Do we yet have results for this mapper ?
        if (!array_key_exists($mapperClass, $this->softDeletedModels)) {
            $this->softDeletedModels[$mapperClass] = new Collection();
        }

        $this->softDeletedModels[$mapperClass] = $this->softDeletedModels[$mapperClass]->merge($deletedModels);
    }


    /**
     * Return a single line string of IDs
     *
     * @param int[] $ids
     * @return string
     */
    private function getIdString(array $ids): string
    {
        $string = '[';
        $first  = true;
        foreach ($ids as $id) {
            if (!$first) {
                $string .= ',';
            }
            $string .= $id;
            $first  = false;
        }
        $string .= ']';

        return $string;
    }
}
