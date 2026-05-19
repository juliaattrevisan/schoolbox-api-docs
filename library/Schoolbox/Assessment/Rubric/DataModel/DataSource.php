<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use PDO;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Mapper as CapabilityMapper;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Model as CapabilityModel;
use Schoolbox\Assessment\Rubric\DataModel\CapabilityMark\Mapper as CapabilityMarkMapper;
use Schoolbox\Assessment\Rubric\DataModel\CapabilityMark\Model as CapabilityMarkModel;
use Schoolbox\Assessment\Rubric\DataModel\CapabilityUsage\Mapper as CapabilityUsageMapper;
use Schoolbox\Assessment\Rubric\DataModel\CapabilityUsage\Model as CapabilityUsageModel;
use Schoolbox\Assessment\Rubric\DataModel\Descriptor\Mapper as DescriptorMapper;
use Schoolbox\Assessment\Rubric\DataModel\Descriptor\Model as DescriptorModel;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as IndicatorMapper;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Model as IndicatorModel;
use Schoolbox\Assessment\Rubric\DataModel\IndicatorMark\Mapper as IndicatorMarkMapper;
use Schoolbox\Assessment\Rubric\DataModel\IndicatorMark\Model as IndicatorMarkModel;
use Schoolbox\Assessment\Rubric\DataModel\IndicatorUsage\Mapper as IndicatorUsageMapper;
use Schoolbox\Assessment\Rubric\DataModel\IndicatorUsage\Model as IndicatorUsageModel;
use Schoolbox\Assessment\Rubric\DataModel\Level\Mapper as LevelMapper;
use Schoolbox\Assessment\Rubric\DataModel\Level\Model as LevelModel;
use Schoolbox\Assessment\Rubric\Grid\CapabilityUsage;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\Grid\Importer\IImporter;
use Schoolbox\Assessment\Rubric\Grid\Importer\CsvImporterFactory;
use Schoolbox\Assessment\Rubric\Grid\IndicatorUsage;
use Schoolbox\Assessment\Rubric\Instance\Model;
use Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark;
use Schoolbox\Assessment\Rubric\MarkedGrid\IndicatorMark;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\Assessment\Rubric\RowSet\Capability;
use Schoolbox\Assessment\Rubric\RowSet\Indicator;
use Schoolbox\Assessment\Rubric\RowSet\RowSet;
use Schoolbox\Assessment\Rubric\RowSet\ValidRowSet;
use Schoolbox\Assessment\Rubric\Util;
use Schoolbox\Assessment\Term\Model as TermModel;
use Schoolbox\Database\Factory;
use Schoolbox\GenericCollection;
use Schoolbox\IMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Analytics\BoxPlot\Summary;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedbackModel;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\ISelfAssessableFeedback;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\User;
use UnderflowException;
use UnexpectedValueException;

/**
 * Data source for rubric related information.
 *
 * @package Schoolbox\Assessment\Rubric
 */
class DataSource
{
    /**
     * Default weight, typically used on fresh csv imports.
     * The idea being default 100 is so that rubrics "just work" out of the box like they always did; having zero weight
     * means it doesn't contribute to the aggregated rubric total. It's really similar to how project summation works.
     */
    private const int RUBRIC_DEFAULT_WEIGHT = 100;


    /**
     * Stores the singleton instance of the mapper
     *
     * @var self[]
     */
    protected static array $instance;


    /**
     * @var IConnector
     */
    protected IConnector $database;


    /**
     * @var UnitSpecificationMapper
     */
    private UnitSpecificationMapper $unitSpecificationMapper;


    /**
     * @var \Schoolbox\Assessment\Rubric\Instance\Mapper
     */
    private \Schoolbox\Assessment\Rubric\Instance\Mapper $rubricInstanceMapper;


    /**
     * @var CapabilityMapper
     */
    private CapabilityMapper $capabilityMapper;


    /**
     * @var IndicatorMapper
     */
    private IndicatorMapper $indicatorMapper;


    /**
     * @var CapabilityUsageMapper
     */
    private CapabilityUsageMapper $capabilityUsageMapper;


    /**
     * @var IndicatorUsageMapper
     */
    private IndicatorUsageMapper $indicatorUsageMapper;


    /**
     * @var DescriptorMapper
     */
    private DescriptorMapper $descriptorMapper;


    /**
     * @var LevelMapper
     */
    private LevelMapper $levelMapper;


    /**
     * @var CapabilityMarkMapper
     */
    private CapabilityMarkMapper $capabilityMarkMapper;


    /**
     * @var IndicatorMarkMapper
     */
    private IndicatorMarkMapper $indicatorMarkMapper;


    /**
     * Despite the namespace, MarkType is a universal idea, not specific to submission boxes (aka Due Work)
     *
     * @var SubmissionBox\MarkTypeMapper
     */
    private SubmissionBox\MarkTypeMapper $markTypeMapper;


    /**
     * @var Group\Mapper
     */
    private Group\Mapper $gradeGroupMapper;


    /**
     * @var StorageMapper
     */
    private StorageMapper $storageMapper;


    /**
     * Constructor
     */
    public function __construct()
    {
        /* We spawn singletons out of nowhere so we probably can't dependency-inject these like we normally would
         */
        $this->database = Factory::getInstanceInternal();
        $this->unitSpecificationMapper = UnitSpecificationMapper::getInstance();
        $this->rubricInstanceMapper = \Schoolbox\Assessment\Rubric\Instance\Mapper::getInstance();
        $this->capabilityMapper = CapabilityMapper::getInstance();
        $this->indicatorMapper = IndicatorMapper::getInstance();
        $this->capabilityUsageMapper = CapabilityUsageMapper::getInstance();
        $this->indicatorUsageMapper = IndicatorUsageMapper::getInstance();
        $this->descriptorMapper = DescriptorMapper::getInstance();
        $this->levelMapper = LevelMapper::getInstance();
        $this->capabilityMarkMapper = CapabilityMarkMapper::getInstance();
        $this->indicatorMarkMapper = IndicatorMarkMapper::getInstance();
        $this->markTypeMapper = SubmissionBox\MarkTypeMapper::getInstance();
        $this->gradeGroupMapper = Group\Mapper::getInstance();
        $this->storageMapper = StorageMapper::getInstance();
    }


    /**
     * Retrieves the singleton instance of this class.
     *
     * @return static
     */
    public static function getInstance(): self
    {
        $class_name = get_called_class();

        if (!isset(self::$instance[$class_name])) {
            self::$instance[$class_name] = new static();
        }

        return self::$instance[$class_name];
    }


    /**
     * Retrieves a rowset for the given context.
     *
     * @todo consider whether this belongs here, or in some other class
     *
     * @param AssessmentContext $context
     * @param bool              $getUsages
     * @param bool              $useCache If false, bypass cache for fetch of capabilities+indicators
     * @return RowSet
     * @throws MapperException
     */
    public function retrieveRowSet(AssessmentContext $context, bool $getUsages, bool $useCache = true): RowSet
    {
        $rowSet = new RowSet($context);

        // Load the capabilities and indicators for this context
        $folder           = $context->getBaseContext()->getFolder();
        $capabilityModels = $this->capabilityMapper->findForFolder($folder->getId(), $useCache);
        $indicatorModels  = $this->indicatorMapper->findForFolder($folder->getId(), $useCache);

        // Unit-specs have their course spec's capabilities/indicators as well
        $courseSpecFolderId = null;

        if ($context instanceof UnitSpecification || $folder->hasUnitSpecification()) {
            $courseSpecFolderId = (int)$folder->getParent()->getId();

            $capabilityModels = $this->capabilityMapper
                ->findForFolder($courseSpecFolderId, $useCache)
                ->merge($capabilityModels);

            $indicatorModels = $this->indicatorMapper
                ->findForFolder($courseSpecFolderId, $useCache)
                ->merge($indicatorModels);
        }

        $capabilities = new GenericCollection(\Schoolbox\Assessment\Rubric\RowSet\Capability::class);

        // Populate the capabilities for this context
        foreach ($capabilityModels as $capabilityModel) {
            $isCourseCapability = $capabilityModel->getParentId() === $courseSpecFolderId;
            $capabilityId       = $capabilityModel->getId();

            if (!isset($capabilities[$capabilityId])) {
                $capabilities[$capabilityId] = (new \Schoolbox\Assessment\Rubric\RowSet\Capability())
                    ->setId($capabilityId)
                    ->setName($capabilityModel->getName())
                    ->setIsCourseLevel($isCourseCapability)
                    ->setCurriculumNodes($capabilityModel->getCurriculumNodes()->reindex());

                if ($getUsages) {
                    foreach ($this->indicatorUsageMapper->findCapabilityUsage($capabilityId) as $capabilityUsage) {
                        $capabilities[$capabilityId]->getUsage()[$capabilityUsage['id']] = (new DueWorkModel())
                            ->setId($capabilityUsage['id'])
                            ->setTitle($capabilityUsage['title']);
                    }
                }
            }
        }


        // Populate the indicators for this context
        foreach ($indicatorModels as $indicatorModel) {
            $capabilityModel = $indicatorModel->getCapability();
            $capabilityId    = $capabilityModel->getId();
            $indicatorId     = $indicatorModel->getId();

            $capabilities[$capabilityId]->getIndicators()[$indicatorId] = (new \Schoolbox\Assessment\Rubric\RowSet\Indicator())
                ->setId($indicatorId)
                ->setName($indicatorModel->getName())
                ->setCurriculumNodes($indicatorModel->getCurriculumNodes()->reindex());

            if ($getUsages) {
                foreach ($this->indicatorUsageMapper->findIndicatorUsage($indicatorId) as $indicatorUsage) {
                    $capabilities[$capabilityId]->getIndicators()[$indicatorId]->getUsage()[$indicatorUsage['id']]
                        = (new DueWorkModel())
                            ->setId($indicatorUsage['id'])
                            ->setTitle($indicatorUsage['title']);
                }
            }
        }

        $rowSet->setCapabilities($capabilities);

        return $rowSet;
    }


    /**
     * Saves this row set to the database.
     *
     * @noinspection PhpDocMissingThrowsInspection Ignore spurious DI exception
     *
     * @param RowSet           $rowSet
     * @param bool             $persistCurriculum    Should curriculum nodes for capabilities and
     *                                               indicators be updated?
     * @param bool             $allowModifyUsed      Should capabilities/indicators that are in use
     *                                               be modifiable or deletable?
     * @param null|IAssessable $allowModifyExclusion If set and `$allowModifyUsed` is off, a
     *                                               capabilities or indicator in `$rowSet` MAY be
     *                                               modified if it is used by no other
     *                                               assessment than this one.
     * @throws MapperException
     * @throws \UnexpectedValueException             - If a duplicate indicator or capability exists in the folder
     *                                               - If course capability duplicates a unit capability
     *                                               - If a capability or indicator that should not be deleted has been
     * @throws \InvalidArgumentException             If an undocumented error occurred whilst persisting
     */
    public function persistRowSet(
        RowSet $rowSet,
        bool $persistCurriculum,
        bool $allowModifyUsed,
        ?IAssessable $allowModifyExclusion = null
    ): void {
        // Where does this go?
        $folder                   = $rowSet->getContext()->getBaseContext()->getFolder();

        // Existing data
        $existingRowSet           = $this->retrieveRowSet($rowSet->getContext(), true);
        $existingCapabilityIds    = $existingRowSet->retrieveCapabilityIds();
        $existingIndicatorIds     = $existingRowSet->retrieveIndicatorIds();
        $existingCapabilityModels = $this->capabilityMapper->findByColumn('id', $existingCapabilityIds);
        $existingIndicatorModels  = $this->indicatorMapper->findByColumn('id', $existingIndicatorIds);

        // New data
        $newCapabilityIds         = $rowSet->retrieveCapabilityIds();
        $newIndicatorIds          = $rowSet->retrieveIndicatorIds();
        $newCapabilityModels      = $this->capabilityMapper->createCollection();
        $newIndicatorModels       = $this->indicatorMapper->createCollection();
        $deletedCapabilityIds     = array_diff($existingCapabilityIds, $newCapabilityIds);
        $deletedIndicatorIds      = array_diff($existingIndicatorIds, $newIndicatorIds);


        // Process capabilities+indicators into models
        foreach ($rowSet->getCapabilities() as $capability) {
            // Set the capability aside to be persisted
            $existingCapability = null;
            $capabilityId       = $capability->getId();
            $capabilityModel    = null;

            if ($capabilityId) {
                $existingCapability = $existingRowSet->retrieveCapability($capability->getId());
                $capabilityModel    = $existingCapabilityModels[$capabilityId] ?? null;
            }

            // If the capability model doesn't exist yet: create it
            // (New capabilities may never be course-level, they are always
            //  created on the unit)
            if (!$capabilityModel) {
                $capabilityModel = $this->capabilityMapper->createModel()
                    ->setId($capabilityId)
                    ->setParentId($folder->getId())
                    ->setParentType(CapabilityModel::PARENT_TYPE_CATEGORY);
            }

            // Validate whether name can be changed
            $allowNameChange = true;

            if ($existingCapability && !$allowModifyUsed && $existingCapability->isInUse($allowModifyExclusion)) {
                $allowNameChange = false;
            }

            if ($allowNameChange) {
                $capabilityModel->setName($capability->getName());
            }

            // Validate whether curriculum code can be changed
            if ($persistCurriculum) {
                $capabilityModel->setCurriculumNodes($capability->getCurriculumNodes());
            }

            // Add capability to list
            $newCapabilityModels->add($capabilityModel);

            // Set each indicator in the capability aside to be persisted
            foreach ($capability->getIndicators() as $indicator) {
                $existingIndicator = null;
                $indicatorId       = $indicator->getId();
                $indicatorModel    = null;

                if ($indicatorId) {
                    $existingIndicator = $existingRowSet->retrieveIndicator($indicatorId);
                    $indicatorModel    = $existingIndicatorModels[$indicatorId] ?? null;
                }

                // If the indicator model doesn't exist yet: create it
                if (!$indicatorModel) {
                    $indicatorModel = $this->indicatorMapper->createModel()
                        ->setId($indicatorId)
                        ->setCapability($capabilityModel);
                }

                // Validate whether name can be changed
                $allowNameChange = true;

                if ($existingIndicator && !$allowModifyUsed && $existingIndicator->isInUse($allowModifyExclusion)) {
                    $allowNameChange = false;
                }

                if ($allowNameChange) {
                    $indicatorModel->setName($indicator->getName());
                }

                // Validate whether curriculum code can be changed
                if ($persistCurriculum) {
                    $indicatorModel->setCurriculumNodes($indicator->getCurriculumNodes());
                }

                // Add indicator to list
                $newIndicatorModels->add($indicatorModel);
            }
        }


        // Forgive me...
        /** @var RosettaStone $l10n */
        /** @noinspection PhpUnhandledExceptionInspection */
        $l10n = \Schoolbox\Mapper::getDi()->get('l10n');

        $spec = new ValidRowSet(
            $l10n,
            $this->unitSpecificationMapper,
            $this,
            $existingRowSet,
            $allowModifyUsed,
            $allowModifyExclusion
        );


        // `isSatisfiedBy` needs to raise an exception instead of just
        // returning false.
        // If it does just return false: that's a logic exception, fix it
        if (!$spec->isSatisfiedBy($rowSet)) {
            throw new \InvalidArgumentException("Unable to save rubric for unknown reason");
        }


        // Save new capabilities+indicators
        $this->capabilityMapper->save($newCapabilityModels);
        $this->indicatorMapper->save($newIndicatorModels);


        // Tidy up unsaved capabilities+indicators
        $this->tidyUp($this->indicatorMapper, ['id' => $deletedIndicatorIds], []);
        $this->tidyUp($this->capabilityMapper, ['id' => $deletedCapabilityIds], []);
    }


    /**
     * Retrieve a blank grid for the given assessment (aka, the default rubric)
     * equivalent to what would be used if an assessment hasn't been persisted yet
     *
     * @param IAssessable $assessment
     * @param bool $getUsages
     * @return Grid
     * @throws MapperException
     */
    public function retrieveBlankGrid(IAssessable $assessment, bool $getUsages): Grid
    {
        /* This $rubricInstance used to inform some default settings of a blank grid.
         *
         * That's kind of weird in the case of a blank grid because createModel() doesn't instantiate something with
         * meaningful default values; they're just null. It relies on swamp to set the meaningful defaults, but that
         * occurs elsewhere. Anyway, for our usages, it's sane enough to assume that "null" equates to "false" which
         * ultimately means "this setting is off"
         *
         * Note that the output of this method is used in the frontend (and its corresponding javascript) when you are
         * creating a whole new grid. The expectations of the javascript are fulfilled in part by the settings here,
         * such as rubric titles never being nullable forcing us to empty-string them initially here.
         */
        $rubricInstance = \Schoolbox\Assessment\Rubric\Instance\Mapper::getInstance()->createModel()
            ->setTitle('')
            ->setWeight(self::RUBRIC_DEFAULT_WEIGHT);

        return new Grid($assessment, $rubricInstance, $this->retrieveRowSet($assessment->getContext(), $getUsages));
    }


    /**
     * Retrieves the grid contents of a particular rubric instance, $rubricInstance.
     *
     * @param $assessment
     * @param \Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance
     * @param bool $getUsages
     * @param bool $cloneNewRubric
     * @return Grid
     * @throws MapperException
     */
    public function retrieveOneGrid(
        $assessment,
        \Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance,
        bool $getUsages,
        bool $cloneNewRubric = false
    ): Grid {
        // Build the rowset first
        $grid = $this->retrieveBlankGrid($assessment, $getUsages);

        // Assessment isn't persisted yet? This has a blank grid
        if (!$assessment->getId()) {
            return $grid;
        }

        // Load the levels, selected capabilities+indicators, descriptors for the rubric instance
        $capabilityUsageModels = $this->capabilityUsageMapper->findByColumn(
            'rubric_instance_id',
            $rubricInstance->getId(),
            ['order' => ['sequence' => 'asc',]]
        )->indexByCallback(function (CapabilityUsageModel $capabilityUsage) {
            return $capabilityUsage->getCapability()->getId();
        });

        $indicatorUsageModels = $this->indicatorUsageMapper->findByColumn(
            'rubric_instance_id',
            $rubricInstance->getId(),
            ['order' => ['sequence' => 'asc',]]
        )->indexByCallback(function (IndicatorUsageModel $indicatorUsage) {
            return $indicatorUsage->getIndicator()->getId();
        });

        $levelModels = $this->levelMapper->findByColumn(
            'rubric_instance_id',
            $rubricInstance->getId(),
            ['order' => ['sequence' => 'asc',]]
        )->indexByCallback(function (LevelModel $l) {
            return $l->getId();
        });

        $descriptorModels = $this->descriptorMapper->findByColumnList([
            'rubric_indicator_id' => $indicatorUsageModels->keys(),
            'level_id' => $levelModels->keys(),
        ]);

        $groupedIndicatorUsageModels = $indicatorUsageModels->groupByCallback(
            function (IndicatorUsageModel $indicatorUsage) {
                return $indicatorUsage->getIndicator()->getCapability()->getId();
            }
        );

        // Create descriptor models
        $descriptorsByLevel     = [];
        $descriptorsByIndicator = [];

        foreach ($descriptorModels as $descriptorModel) {
            $levelId     = $descriptorModel->getLevel()->getId();
            $indicatorId = $descriptorModel->getIndicator()->getId();
            // TODO: check if setting to null when cloning is okay or not
            $descriptorId = $cloneNewRubric ? null : $descriptorModel->getId();

            $descriptor = (new \Schoolbox\Assessment\Rubric\Grid\Descriptor())
                ->setId($descriptorId)
                ->setDescription($descriptorModel->getDescription())
                ->setMaxValue($descriptorModel->getMaxValue());

            $descriptorsByIndicator[$indicatorId][$levelId] = $descriptor;
            $descriptorsByLevel[$levelId][$indicatorId] = $descriptor;
        }


        // Add levels
        foreach ($levelModels as $levelModel) {
            $levelId = $cloneNewRubric ? null : $levelModel->getId();
            // TODO: check if setting to null when cloning is okay or not
            $level = (new \Schoolbox\Assessment\Rubric\Grid\Level())
                ->setId($levelId)
                ->setName($levelModel->getName());

            $grid->getLevels()->add($level);
        }


        // Add capability/indicator usages
        // Respect prescribed ordering in usage objects
        foreach ($capabilityUsageModels as $capabilityUsageModel) {
            /** @var CapabilityUsageModel $capabilityUsageModel */
            $capabilityModel = $capabilityUsageModel->getCapability();
            $capabilityId    = $capabilityModel->getId();
            $capability      = $grid->getRowSet()->retrieveCapability($capabilityId);

            if (!$capability) {
                continue;
            }

            $capabilityUsage = new \Schoolbox\Assessment\Rubric\Grid\CapabilityUsage($capability);

            $capabilityIndicatorUsageModels = $groupedIndicatorUsageModels[$capabilityId] ?? [];

            foreach ($capabilityIndicatorUsageModels as $indicatorUsageModel) {
                /** @var IndicatorUsageModel $indicatorUsageModel */
                $indicatorModel = $indicatorUsageModel->getIndicator();
                $indicatorId    = $indicatorModel->getId();
                $indicator      = $grid->getRowSet()->retrieveIndicator($indicatorId);

                if (!$indicator) {
                    continue;
                }

                $indicatorUsage = new \Schoolbox\Assessment\Rubric\Grid\IndicatorUsage($indicator);

                // Add descriptors, and apply min value
                $levelIndex = 0;

                foreach ($levelModels as $levelModel) {
                    $descriptor = $descriptorsByIndicator[$indicatorId][$levelModel->getId()] ?? null;

                    if (null !== $descriptor) {
                        $indicatorUsage->getDescriptors()[$levelIndex] = $descriptor;
                    }

                    $levelIndex++;
                }

                $indicatorUsage->applyDescriptorMinValues($assessment->getUseMarkRange());

                $capabilityUsage->getIndicatorUsages()[$indicatorId] = $indicatorUsage;
            }

            // Max value is derived from indicators, if mark-per-cap is off
            $capabilityUsage->setMaxValue(
                $rubricInstance->isMarkPerCapability() ? $capabilityUsageModel->getMaxValue() : $capabilityUsage->calculateMaxValueFromIndicators()
            );

            $grid->getCapabilityUsages()[$capabilityId] = $capabilityUsage;
        }

        /* This is super important and it's a bit shameful to -need- this...but after the $grid is built, we need to
         * assign the provided $rubricInstance to it. Otherwise, the $grid->getRubricInstance() will return you a grid
         * with the _stub_ that was used to manufacture the Blank Grid right at the start of this method in
         * retrieveBlankGrid() which isn't helpful to you anywhere else in the app.
         */
        $grid->setRubricInstance($rubricInstance);

        /* Reset the assessment id in rubric grid for the cloned copy
         */
        if ($cloneNewRubric) {
            /* Cloning to preserve the id of the original assessment
             */
            $cloneAssessment = clone $assessment;
            $grid = $grid->setAssessment($cloneAssessment->setId(null));

            /* TODO MAYBE this is a place where a stub rubric_instance must exist
             *  That's because the cloned grid shouldn't know anything about its cloned rubric instance, otherwise we
             *  might end up sharing a rubric_instance across multiple assessments which isn't correct at all.
             *  You'll find out when you're trying to import courses to test this, anyways.
             */
            // $grid->getRubricInstance()->setId(null); ?????????????????
        }

        return $grid;
    }


    /**
     * Retrieve a grid for the given assessment.
     *
     * @param DueWorkModel $assessment
     * @param bool         $getUsages
     * @param bool $cloneNewRubric if true, it should then return a copy of the rubric grid
     *                             without the identifier, and can be used to create a clone assessment
     *
     * @return Grid
     * @throws MapperException
     */
    public function retrieveGrid(DueWorkModel $assessment, bool $getUsages, bool $cloneNewRubric = false): Grid
    {
        /* TODO this place will snap loudly by intent
         *  This will probably become retrieveGrids(), iteratively fetching each grid of an $assessment and returning
         *  a set of associated rubric grids.
         *
         * $stack = [];
         *
         * foreach ($assessment->getRubricInstances() as $rubricInstance()) {
         *     $stack[] = $this->retrieveOneGrid($assessment, $rubricInstance, ...);
         * }
         *
         * return $stack;
         */
    }


    /**
     * Saves this grid to the database.
     *
     * @param Grid $newGrid
     * @param null|Grid $originalGrid If set, this is the state of the assessment's grid pre-save
     * @return void
     * @throws \UnexpectedValueException If a duplicate indicator or capability exists in the folder
     * @throws \UnexpectedValueException If course capability duplicates a unit capability
     * @throws \UnexpectedValueException If a capability or indicator that should not be deleted has been
     * @throws \InvalidArgumentException If an undocumented error occurred whilst persisting
     * @throws Deleted
     * @throws MapperException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function persistGrid(Grid $newGrid, ?Grid $originalGrid): void
    {
        // TODO some way to wrap all this in a transaction would be nice... -_-;
        // (The answer exists: merge SA-6405)
        // TODO figure out this ^^^^^ hideous historical atrocity

        /* This is the corresponding rubric_instance used in the new grid. Note this could contain a negative id,
         * indicating it has never been persisted before: it's a whole new rubric instance.
         */
        $rubricInstance = $newGrid->getRubricInstance();

        /* If this rubric instance was never saved before, save it now, so it has an identifier.
         * Otherwise, save it anyway, in case its title/weight/settings were updated.
         * lol
         */
        if ($rubricInstance->getId() < 0) {
            $rubricInstance->setId(null);
        }

        /* Note that atm we're wrapped in some kind of transaction: this doesn't save if an error occurs later.
         * This alludes to the SA-6405 mystery which seems to MAYBE negate the docblock at the top of this method.
         * jeez
         */
        $this->rubricInstanceMapper->save($rubricInstance);

        // First, persist the levels
        $levelModels   = $this->levelMapper->createCollection();
        $levelSequence = 1;

        foreach ($newGrid->getLevels() as $level) {
            $levelModel = $this->levelMapper->createModel()
                ->setId($level->getId())
                ->setName($level->getName())
                ->setRubricInstance($rubricInstance)
                ->setSequence($levelSequence++);

            $levelModels->add($levelModel);

            // Need to save NOW so that I have an ID which can then be assoc'd
            // with a descriptor
            $this->levelMapper->save($levelModel);

            $level->setId($levelModel->getId());
        }


        // Persist capabilities+indicators
        $indicatorModels  = $this->indicatorMapper->createCollection();
        $capabilityModels = $this->capabilityMapper->createCollection();

        foreach ($newGrid->getRowSet()->getCapabilities() as $capability) {
            $capabilityId = $capability->getId();

            if (!$capabilityId) {
                continue;
            }

            /* Update the name of an already-existing capability
             *
             * TODO This can probably be shortcutted by using findByColumn() and a hashtable lookup idea instead,
             *  minimising the number of db queries being performed here
             */
            $capabilityModel = $this->capabilityMapper->findById($capabilityId)
                ->setName($capability->getName());

            $capabilityModels[$capabilityId] = $capabilityModel;

            foreach ($capability->getIndicators() as $indicator) {
                $indicatorId = $indicator->getId();

                if (!$indicatorId) {
                    continue;
                }

                /* Update the name of an already-existing indicator
                 *
                 * TODO This can probably be shortcutted by using findByColumn() and a hashtable lookup idea instead
                 *  minimising the number of db queries being performed here
                 */
                $indicatorModel = $this->indicatorMapper->findById($indicatorId)
                    ->setName($indicator->getName());

                $indicatorModels[$indicatorId] = $indicatorModel;
            }
        }


        // Can validate the grid's rowset the same way I would in persistRowset
        // (Forgive me too for perpetuating this)
        /** @var RosettaStone $l10n */
        $l10n = \Schoolbox\Mapper::getDi()->get('l10n');

        $spec = new ValidRowSet(
            $l10n,
            $this->unitSpecificationMapper,
            $this,
            $originalGrid ? $originalGrid->getRowSet() : null,
            false,
            $newGrid->getAssessment()
        );


        // `isSatisfiedBy` needs to raise an exception instead of just
        // returning false.
        // If it does just return false: that's a logic exception, fix it
        if (!$spec->isSatisfiedBy($newGrid->getRowSet())) {
            throw new \InvalidArgumentException("Save of row set failed for unknown reason");
        }


        // usages are a join table with a sequence, not a strict "object" - as such, we can purge the relevant records and simply re-create them
        $indicatorUsageModels  = $this->indicatorUsageMapper->createCollection();
        $capabilityUsageModels = $this->capabilityUsageMapper->createCollection();
        $descriptorModels      = $this->descriptorMapper->createCollection();

        /* TODO Find out why this is so vitally important to be 1-indexed instead of 0-indexed.
         */
        $capabilitySequence = CapabilityUsageModel::SEQUENCE_BASE;

        /* Clear away any previous usages of caps and indicators via this rubric instance identifier
         * New grids won't have any, but old grids might.
         */
        $this->indicatorUsageMapper->deleteByColumn('rubric_instance_id', $rubricInstance->getId());
        $this->capabilityUsageMapper->deleteByColumn('rubric_instance_id', $rubricInstance->getId());

        foreach ($newGrid->getCapabilityUsages() as $capabilityUsage) {
            $capabilityMaxValue = $capabilityUsage->getMaxValue();

            $capabilityUsageModel = $this->capabilityUsageMapper->createModel()
                ->setRubricInstance($rubricInstance)
                ->setCapability($capabilityModels[$capabilityUsage->getCapability()->getId()])
                ->setMaxValue($capabilityMaxValue)
                ->setSequence($capabilitySequence++);

            $capabilityUsageModels->add($capabilityUsageModel);

            /* Increment sequence throughout the foreach, assuming it's given to us in the intended order
             */
            $indicatorSequence = IndicatorUsageModel::SEQUENCE_BASE;

            foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
                $indicatorModel = $indicatorModels[$indicatorUsage->getIndicator()->getId()];

                $indicatorUsageModel = $this->indicatorUsageMapper->createModel()
                    ->setRubricInstance($rubricInstance)
                    ->setIndicator($indicatorModel)
                    ->setMaxValue($indicatorUsage->calculateMaxValueFromDescriptors())
                    ->setSequence($indicatorSequence++);

                $indicatorUsageModels->add($indicatorUsageModel);

                // Descriptors
                foreach ($newGrid->getLevels() as $levelIndex => $level) {
                    $descriptor = $indicatorUsage->retrieveDescriptor($levelIndex);

                    if ($descriptor) {
                        $descriptorModel = $this->descriptorMapper->createModel()
                            ->setId($descriptor->getId())
                            ->setIndicator($indicatorModel)
                            ->setLevel($this->levelMapper->createModel($level->getId()))
                            // One day: set min value here
                            ->setMaxValue($descriptor->getMaxValue())
                            ->setDescription($descriptor->getDescription());

                        $descriptorModels->add($descriptorModel);
                    }
                }
            }
        }

        $this->capabilityMapper->save($capabilityModels);
        $this->indicatorMapper->save($indicatorModels);
        $this->capabilityUsageMapper->save($capabilityUsageModels);
        $this->indicatorUsageMapper->save($indicatorUsageModels);
        $this->descriptorMapper->save($descriptorModels);


        // wash up before dinner, kids


        // Invalidate all marks, if the grid and original grid are incompatible
        if ($originalGrid && !$newGrid->isCompatibleWith($originalGrid)) {
            /* TODO This needs fixing: submission returns are here
             */
            $this->invalidateMarks($newGrid->getAssessment()->getId());
        }


        // remove all levels that have zero descriptors attached - extract from the descriptors
        $seenLevels = [];

        foreach ($descriptorModels as $desc) {
            $seenLevels[$desc->getLevel()->getId()] = true;
        }

        $seenLevels = array_keys($seenLevels);

        $this->tidyUp($this->levelMapper, ['rubric_instance_id' => $rubricInstance->getId()], $seenLevels);

        // remove all descriptors that are on this box, but are not among the descriptors we just saved (it indicates blankness / unused-ness)
        /* This ^^^ probably means: manage the diff between descriptors that -were- here previously, but no longer;
         * this suggests the user deselected them in the user interface
         */
        $levelIds = $this->levelMapper->findIdsByColumn('rubric_instance_id', $rubricInstance->getId(), ['deleted' => true]);
        $this->tidyUp($this->descriptorMapper, ['level_id' => $levelIds], $descriptorModels->getPropertyValues('id'));
    }


    /**
     * Retrieve a marked grid for the given feedback.
     *
     * @param IFeedback $feedback
     * @param Model $rubricInstance
     * @return MarkedGrid
     * @throws MapperException
     */
    public function retrieveMarkedGrid(IFeedback $feedback, \Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance): MarkedGrid
    {
        $assessable = $feedback->getAssessable();

        $grid = $this->retrieveOneGrid($assessable, $rubricInstance, false);
        $rowSet = $grid->getRowSet();

        // Get the mark scheme for the grid
        $markSchemeFactory = new MarkSchemeFactory();

        /* Learner and instructor mark schemes might differ; use them accordingly.
         *    - Learners should see the learner mark scheme (this might even be the same as the instructor mark scheme)
         *    - Instructors should see the instructor mark scheme.
         *
         * TODO Technical Uplift Candidate
         *  Instead of checking concrete domain objects, check the fulfilment of interface IDualMarkType
         *  Though I have misgivings about that name in its entirety, we'll have to live with it for now.
         *  A MarkScheme is mainly a codec for converting normalised to human readable marks and vice versa.
         */

        /* Assume learner mark scheme initially
         */
        $gridMarkScheme = $assessable->getMarkScheme();

        /* Due Work and Reporting are the only two activities that support dual mark types
         */
        if ($assessable instanceof \Schoolbox\Learning\Assessment\Reporting\Model) {
            $gridMarkScheme = $assessable->getInstructorMarkScheme();
        } else {
            /* Check if this feedback instance supports self assessment. If it does (only due work supports this atm)
             * then use the learner's mark scheme. If the feedback instance doesn't support self assessment then it's
             * been given by the instructor and we should use their mark scheme instead of the learner's.
             */
            $isSelfAssessment = $feedback instanceof ISelfAssessableFeedback && $feedback->isSelfAssessment();

            if (!$isSelfAssessment && $assessable instanceof \Schoolbox\Learning\Assessment\DueWork\Model) {
                $gridMarkScheme = $assessable->getInstructorMarkScheme();
            }
        }

        $markedGrid = new MarkedGrid($feedback, $grid, $gridMarkScheme);

        /* The feedback instance determines ultimately which schema we explore to find capability marks (ugh!)
         * This is pretty horrible but I don't really have many answers for a polymorphic schema like this
         */
        if ($feedback instanceof \Schoolbox\Learning\Assessment\DueWork\Feedback\Model) {
            $feedbackTable = SubmissionBox\ReturnedWorkMapper::getInstance()->getTable();
        } elseif ($feedback instanceof \Schoolbox\Learning\Assessment\Reporting\Feedback\Model) {
            $feedbackTable = \Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Mapper::getInstance()->getTable();
        } else {
            throw new \InvalidArgumentException('Unknown feedback table derived from feedback instance.');
        }

        // What goes in the grid?
        // * Capability marks
        // * Descriptor marks
        // ** more correctly known as indicator marks, which reference a descriptor
        /** @var GenericCollection<CapabilityMarkModel> $capabilityMarkModels */
        $capabilityMarkModels = $this->capabilityMarkMapper->findByColumnList([
            'feedback_id' => $feedback->getId(),
            'feedback_table' => $feedbackTable,
            'valid' => true,
        ]);

        /** @var GenericCollection<IndicatorMarkModel> $indicatorMarkModelsByCapability */
        $indicatorMarkModelsByCapability = $this->indicatorMarkMapper->findByColumnList([
            'rubric_capability_mark_id' => $capabilityMarkModels->getPropertyValues('id'),
            'valid' => true,
        ])->filter(function (IndicatorMarkModel $indicatorMark) {
            return null !== $indicatorMark->getDescriptor();
        })->groupByCallback(
            function (IndicatorMarkModel $indicatorMark) {
                return $indicatorMark->getCapabilityMark()->getCapability()->getId();
            }
        );

        // Still only one selected descriptor per indicator
        $indicatorMarkModelsByCapability = $indicatorMarkModelsByCapability->map(
            function (GenericCollection $c) {
                return $c->indexByCallback(
                    function (IndicatorMarkModel $mark) {
                        return $mark->getDescriptor()->getIndicator()->getId();
                    }
                );
            }
        );


        // Load em into the marked grid
        foreach ($capabilityMarkModels as $capabilityMarkModel) {
            // Load the capability
            $capabilityId = $capabilityMarkModel->getCapability()->getId();
            $capability   = $rowSet->retrieveCapability($capabilityId);

            if (!$capability) {
                continue;
            }

            // Load its usage
            $capabilityUsage = $grid->retrieveCapabilityUsage($capability);

            if (!$capabilityUsage) {
                continue;
            }

            // Create the cap mark
            $capabilityMarkScheme = $markSchemeFactory->spawnForCapability(
                $feedback->getAssessable(),
                $capabilityUsage->getMaxValue()
            );

            /* This is actually a MarkedGrid/CapabilityMark instance despite how it might read; don't confuse it with a
             * DataModel/CapabilityMark instance
             */
            $capabilityMark = (new CapabilityMark($capabilityUsage, $capabilityMarkScheme))
                ->setHumanReadableValue($capabilityMarkModel->getHumanReadableValue())
                ->setValue($capabilityMarkModel->getValue());

            // Associate indicator mark models with indicator marks
            $indicatorMarkModels = $indicatorMarkModelsByCapability[$capabilityId] ?? [];

            foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
                $indicatorId = $indicatorUsage->getIndicator()->getId();

                // Which indicator mark model is the right one?
                $indicatorMarkModel = $indicatorMarkModels[$indicatorId] ?? null;

                if (!$indicatorMarkModel) {
                    continue;
                }

                // Load descriptor model
                $descriptorModel = $indicatorMarkModel->getDescriptor();

                if (!$descriptorModel) {
                    continue;
                }

                $descriptorId = $descriptorModel->getId();

                // Create the ind mark
                /* This is actually a MarkedGrid/IndicatorMark instance despite how it might read; don't confuse it with
                 * a DataModel/IndicatorMark instance
                 */
                $indicatorMark = (new IndicatorMark($indicatorUsage))
                    ->setValue($indicatorMarkModel->getValue());

                // Associate the ind mark with its descriptor
                foreach ($indicatorUsage->getDescriptors() as $descriptor) {
                    if ($descriptor->getId() === $descriptorId) {
                        $indicatorMark->setDescriptor($descriptor);
                        break;
                    }
                }

                $capabilityMark->getIndicatorMarks()->add($indicatorMark);
            }

            $markedGrid->getCapabilityMarks()->add($capabilityMark);
        }

        return $markedGrid;
    }


    /**
     * Save this marked grid to the database.
     *
     * @param MarkedGrid $markedGrid
     * @throws MapperException if something wasn't found
     */
    public function persistMarkedGrid(MarkedGrid $markedGrid): void
    {
        // Required mappers
        $returnedWorkMapper = SubmissionBox\ReturnedWorkMapper::getInstance();

        // Blow away any previous cap/ind marks for this feedback
        $returnId = $markedGrid->getFeedback()->getId();

        $this->indicatorMarkMapper->deleteFeedbackMarks($returnId);
        $this->capabilityMarkMapper->deleteFeedbackMarks($returnId);

        // What am I saving marks for?
        $returnedWork = $returnedWorkMapper->findById($returnId);

        // Create list of cap/ind marks that will be saved
        $capabilityMarksToSave = $this->capabilityMarkMapper->createCollection();
        $indicatorMarksToSave  = $this->indicatorMarkMapper->createCollection();

        foreach ($markedGrid->getCapabilityMarks() as $capabilityMark) {
            // Create cap mark
            $capabilityId = $capabilityMark->getCapabilityUsage()->getCapability()->getId();
            $capabilityModel = $this->capabilityMapper->findById($capabilityId);

            $capabilityMarksToSave[$capabilityId] = $capabilityMarkModel = $this->capabilityMarkMapper->createModel()
                ->setSubmissionReturn($returnedWork)
                ->setCapability($capabilityModel)
                ->setValue($capabilityMark->getValue())
                ->setHumanReadableValue($capabilityMark->getHumanReadableValue())
                ->setValid(true);

            // Create ind marks
            foreach ($capabilityMark->getIndicatorMarks() as $indicatorMark) {
                $indicatorId = $indicatorMark->getIndicatorUsage()->getIndicator()->getId();
                $descriptorModel = null;

                if ($indicatorMark->getDescriptor()) {
                    $descriptorModel = $this->descriptorMapper->findById($indicatorMark->getDescriptor()->getId());
                }

                if ($descriptorModel === null) {
                    // Do not create, if descriptor is not set
                    continue;
                }

                $indicatorMarksToSave[$indicatorId] = $this->indicatorMarkMapper->createModel()
                    ->setCapabilityMark($capabilityMarkModel)
                    ->setDescriptor($descriptorModel)
                    ->setValue($indicatorMark->getValue())
                    ->setValid(true);
            }
        }

        // And save cap/ind marks
        $this->capabilityMarkMapper->save($capabilityMarksToSave);
        $this->indicatorMarkMapper->save($indicatorMarksToSave);
    }


    /**
     * Does this participant have a valid instructor marked grid?
     *
     * @param Participant $participant
     * @return bool
     */
    public function hasInstructorMarkedGrid(Participant $participant): bool
    {
        return $this->hasMarkedGrid($participant, [SubmissionBox\ReturnedWork::REVIEW_ROUTE_INSTRUCTOR]);
    }


    /**
     * Does this participant have a valid self-assessed marked grid?
     *
     * @param Participant $participant
     * @return bool
     */
    public function hasSelfAssessedMarkedGrid(Participant $participant): bool
    {
        return $this->hasMarkedGrid(
            $participant,
            [
                SubmissionBox\ReturnedWork::REVIEW_ROUTE_SELF_REFLECTION,
                SubmissionBox\ReturnedWork::REVIEW_ROUTE_SELF_GRADING
            ]
        );
    }


    /**
     * Does this participant have a valid marked grid of the given types?
     *
     * @param Participant $participant
     * @param array       $feedbackTypes The types of feedback to check.
     *                                   One or more of the
     *                                   `\Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork::REVIEW_ROUTE_*`
     *                                   constants.
     * @return bool
     */
    private function hasMarkedGrid(Participant $participant, array $feedbackTypes): bool
    {
        // Which feedbacks are getting checked?
        $feedbackIds = [];

        foreach ($participant->getAllFeedbacks() as $feedback) {
            if (
                $feedback instanceof DueWorkFeedbackModel
                && \in_array($feedback->getReviewRoute(), $feedbackTypes, true)
            ) {
                $feedbackIds[] = $feedback->getId();
            }
        }

        if (empty($feedbackIds)) {
            return false;
        }

        // Determine if there's valid capability marks for this participant.
        // Don't need to check descriptor marks separately, because they cannot
        // exist without capability marks.
        $validCapMarkCount = $this->indicatorMarkMapper->countByColumnList([
            'cm.feedback_id' => $feedbackIds,
            'valid' => true,
        ]);

        return $validCapMarkCount > 0;
    }

    /**
     * Imports a rubric grid from a CSV into the given due work.
     *
     * @param IStorage     $file
     * @param DueWorkModel $dueWork
     * @param int          $maxLevels
     *
     * @throws UnexpectedValueException if the importer couldn't be built
     * @throws UnexpectedValueException if a capability or indicator could not
     *                                  be found after it was supposedly created
     * @throws MapperException          if something happens whilst retrieving
     *                                  or persisting the rowset
     * @return Grid
     */
    public function importGridFromCsv(IStorage $file, DueWorkModel $dueWork, int $maxLevels): Grid
    {
        // Verify that the file is actually a CSV.
        // (I think extension checking is the most reliable way to do this. We
        //  have had issues with mimetype checking in the past, because the CSV
        //  mimetype isn't always recognized by the browser's operating system.)
        if (!(strtolower($file->getExtension()) === 'csv')) {
            throw new UnexpectedValueException('Only CSV files may be uploaded');
        }

        // Create the factory
        $filesystemProxy = $this->storageMapper->getStorageFilesystemProxy();

        $importer = (new CsvImporterFactory($this->markTypeMapper))->build($filesystemProxy->readStream($file), $maxLevels, $dueWork);
        return $this->importGrid($dueWork, $importer, $file->getFilename());
    }


    /**
     * Imports a rubric grid using importer into the given due work.
     *
     * @param IAssessable $assessment
     * @param IImporter $importer
     * @param string $title This becomes the title of the rubric grid at its destination.
     *                      This is either derived from a csv filename or a copy of an original grid's title when changing
     *                      the context of where an $assessment exists (such as moving it between units/projects) but you
     *                      could just arbitrarily give it a name of your choosing, like Bastian did.
     * @return Grid
     * @throws MapperException
     */
    public function importGrid(IAssessable $assessment, IImporter $importer, string $title): Grid
    {
        // Add to the rowset as necessary
        $rowSet        = $this->retrieveRowSet($assessment->getContext(), false);
        $rowsetChanged = false;

        foreach ($importer->retrieveCapabilitiesWithIndicators() as $capability) {
            // Is this capability within the rowset? If not add it
            $capName        = $capability['name'];
            $thisCapability = $rowSet->retrieveCapabilityByName($capName);

            if (!$thisCapability) {
                $rowsetChanged = true;

                /* Create a new rowset capability
                 */
                $thisCapability = (new Capability())
                    ->setName($capName)
                    ->setIsCourseLevel(false);

                $rowSet->getCapabilities()->add($thisCapability);
            }

            foreach ($capability['indicators'] as $indicator) {
                // Is this indicator in the capability? If not add it
                $indName       = $indicator['name'];
                $thisIndicator = $thisCapability->retrieveIndicatorByName($indName);

                if (!$thisIndicator) {
                    $rowsetChanged = true;

                    /* Create a new rowset indicator
                     */
                    $thisIndicator = (new Indicator())
                        ->setName($indName);

                    $thisCapability->getIndicators()->add($thisIndicator);
                }
            }
        }

        // Was the rowset changed? Preserve it if so
        if ($rowsetChanged) {
            $this->persistRowSet($rowSet, true, false);
            // Reload it, bypassing the caches created by the last load I did
            $rowSet = $this->retrieveRowSet($assessment->getContext(), false, false);
        }


        // Now start building the grid
        $capabilityMarkType   = $this->markTypeMapper->createModel()->setId($importer->getCapabilityMarkType());
        $capabilityGradeGroup = $this->gradeGroupMapper->createModel()->setId($importer->getCapabilityGradeGroup());

        /* Spawn a rubric instance for this due work.
         * The rubric instance contains the settings for a grid.
         *
         * TODO Technical Uplift Candidate
         *  This is such a strange in-situ hydration/construction idea, idk why I'm doing it. There must be a more
         *  convenient way to set this up (@armatronic I'm probably braindead atm, there's probably an obvious answer)
         */
        $assessmentModel = \Schoolbox\Learning\Assessment\DataModel\Mapper::getInstance()->createModel()
            ->setId($assessment->getId());

        /* Fulfil rubric settings
         */
        $rubricInstance = $this->rubricInstanceMapper->createModel()
            ->setTitle($title)
            ->setWeight(self::RUBRIC_DEFAULT_WEIGHT)
            ->setAssessmentModel($assessmentModel)
            ->setCapabilityMarkType($capabilityMarkType)
            ->setCapabilityGradeGroup($capabilityGradeGroup)
            ->setMarkedUsingRange($importer->isUseMarkRange());

        $assessment->getRubricInstances()->add($rubricInstance);

        /* $this->rubricInstanceMapper->save($rubricInstance);
         *
         * You can't save it at this point: $assessmentModel might be a new instance and not yet have an identifier.
         * You can encounter this scenario via the rubric controller:
         *
         * elseif ($folderId = $post->get('folderId')) {
         *     $assessment = (new DueWork())
         *         ->setId(0)
         *
         * Persistence must occur later.
         */
        $grid = new Grid($assessment, $rubricInstance, $rowSet);

        // Create levels
        foreach ($importer->getLevels() as $i => $levelName) {
            $grid->getLevels()[$i] = (new \Schoolbox\Assessment\Rubric\Grid\Level())
                ->setName($levelName);
        }


        // Add cap usages, ind usages, descriptors
        foreach ($importer->retrieveCapabilitiesWithIndicators() as $capability) {
            $capInUse = false;

            // If $capabilityObject is null here, that's a problem:
            // it should've been created
            $capabilityObject = $rowSet->retrieveCapabilityByName($capability['name']);

            if (!$capabilityObject) {
                throw new UnexpectedValueException(
                    "Capability {$capability['name']} could not be found even after it was created"
                );
            }

            $capabilityUsage = (new CapabilityUsage($capabilityObject))
                ->setMaxValue($capability['maxValue']);

            foreach ($capability['indicators'] as $indicator) {
                // Likewise if $indicatorObject is null here, that's a problem
                $indicatorObject = $capabilityObject->retrieveIndicatorByName($indicator['name']);

                if (!$indicatorObject) {
                    throw new UnexpectedValueException(
                        "Indicator {$indicator['name']} could not be found even after it was created"
                    );
                }

                $indicatorUsage = new IndicatorUsage($indicatorObject);

                foreach ($indicator['descriptors'] as $level => $descriptor) {
                    if ($descriptor !== null) {
                        $capInUse = true;
                        $indicatorUsage->getDescriptors()[$level] = (new \Schoolbox\Assessment\Rubric\Grid\Descriptor())
                            ->setDescription($descriptor['name'])
                            ->setMaxValue($descriptor['value']);
                    }
                }

                $indicatorUsage->applyDescriptorMinValues($importer->isUseMarkRange());
                $capabilityUsage->getIndicatorUsages()->add($indicatorUsage);
            }

            if ($capInUse) {
                if (null === $capability['maxValue']) {
                    $capabilityUsage->setMaxValue($capabilityUsage->calculateMaxValueFromIndicators());
                }

                $grid->getCapabilityUsages()->add($capabilityUsage);
            }
        }

        return $grid;
    }


    /**
     * Copies the selected descriptors from one submission return to another.
     *
     * @param int $sourceReturnId
     * @param int $destinationReturnId
     * @throws \Schoolbox\Mapper\Exception
     */
    public function copySelections(int $sourceReturnId, int $destinationReturnId)
    {
        // Don't do anything if the source and dest are the same
        if ($sourceReturnId === $destinationReturnId) {
            return;
        }

        /* TODO this has to be agnostic to submission returns and due work in general.
         */
        $sourceDescriptorMarks = $this->indicatorMarkMapper->findByColumn('c.return_id', $sourceReturnId);

        $destinationCapabilityMarks = $this->capabilityMarkMapper->createCollection();
        $destinationDescriptorMarks = $this->indicatorMarkMapper->createCollection();

        $destinationReturn          = SubmissionBox\ReturnedWorkMapper::getInstance()->createModel($destinationReturnId);

        foreach ($sourceDescriptorMarks as $sourceDescriptor) {
            // Is this capability marks set?
            $sourceCapabilityMark = $sourceDescriptor->getCapabilityMark();
            $capability           = $sourceCapabilityMark->getCapability();
            $capabilityId         = $capability->getId();

            if (!$destinationCapabilityMarks[$capabilityId]) {
                $destinationCapabilityMarks[$capabilityId] = $this->capabilityMarkMapper->createModel()
                    ->setCapability($capability)
                    ->setSubmissionReturn($destinationReturn)
                    ->setValue($sourceCapabilityMark->getValue())
                    ->setHumanReadableValue($sourceCapabilityMark->getHumanReadableValue())
                    ->setValid($sourceCapabilityMark->isValid());
            }

            $destinationDescriptorMarks->add(
                $this->indicatorMarkMapper->createModel()
                    ->setCapabilityMark($destinationCapabilityMarks[$capabilityId])
                    ->setDescriptor($sourceDescriptor->getDescriptor())
                    /* TODO For some reason this was disabled when I got here, but idk why
                     */
                //                    ->setSubmissionReturn($destinationReturn)
                    ->setValue($sourceDescriptor->getValue())
                    ->setValid($sourceDescriptor->isValid())
            );
        }

        $this->capabilityMarkMapper->save($destinationCapabilityMarks);
        $this->indicatorMarkMapper->save($destinationDescriptorMarks);
    }


    /**
     * Invalidate ALL the rubric marks given for this assessment.
     *
     * This includes:
     * * capability marks
     * * descriptor marks
     *
     * @param int $assessmentId
     */
    private function invalidateMarks(int $assessmentId): void
    {
        /* TODO replace this whole return_id idea when we get up to reusing this method; I suspect that a particular
         *  grid becomes invalid once you change something important about its setup. It probably doesn't require you to
         *  update *ALL GRIDS* for a given assessment.
         */

        /* Invalidate rubric grids for due works' submission_return records
         */
        $query = "
            UPDATE rubric_indicator_mark rim
            INNER JOIN rubric_capability_mark rcm ON rim.rubric_capability_mark_id = rcm.id
            INNER JOIN submission_return sr ON rcm.feedback_id  = sr.id AND rcm.feedback_table = 'submission_return'
            INNER JOIN submission_box sb ON sr.box_id = sb.id
            SET rim.valid = FALSE,
                rcm.valid = FALSE
            WHERE sb.assessment_id = :assessmentId
        ";

        $this->database->prepare($query)->execute(['assessmentId' => $assessmentId]);

        /* Repeat for all other assessments' feedback types, such as reporting_feedback et al.
         */
    }


    /**
     * A polymorphic method that uses a corresponding $mapper to delete indicator mark instances (or level instances)
     * From the set of models derived by findByColumnList($search), remove all of them except the ones in $exclude
     *
     * Therefore, $exclude thus contains the ones we want to keep, and we need to delete the rest.
     *
     * Hopefully this explanation saves you from having to figure out "the deletion of things that are not in the array
     * of items that shouldn't be deleted"..............)
     *
     * @param IMapper $mapper
     * @param array $search
     * @param array $exclude
     *
     * @return bool
     * @throws MapperException
     */
    protected function tidyUp(IMapper $mapper, array $search, array $exclude): bool
    {
        return $mapper->delete(
            $mapper->findByColumnList($search)->filter(
                function ($model) use ($exclude) {
                    /** @var IndicatorMarkModel|LevelModel $model */
                    return !in_array($model->getId(), $exclude);
                }
            )
        );
    }


    /**
     * Specialised query for returning the longitudinal cross-section of rubric data for a
     * student and set of DueWorks. Data is returned in a primitive hierarchy of:
     *   - capability
     *   - indicator
     *   - assessment
     *   - descriptor (student result)
     *
     * @param GenericCollection<DueWorkModel> $dueWorks
     * @param User                             $student
     * @param bool                             $includeComparison Include other students results for comparison
     *
     * @return array $longitude
     */
    public function getLongitudinal(GenericCollection $dueWorks, User $student, bool $includeComparison): array
    {
        if (0 === $dueWorks->count()) {
            return [];
        }

        $dueWorks->indexBy('id');

        [$feedbackIds, $allFeedbackIds] = $this->getFeedbackIdsWithRubric($dueWorks, $student, $includeComparison);

        if (empty($feedbackIds)) {
            return [];
        }

        // Find rubric used in the assessments
        $pb = new Builder($this->database->getQuoter(), 'vr');
        $pb->in('a.id', array_keys($feedbackIds));

        $where = implode(' AND ', $pb->getWhere());

        $capabilityStatement = $this->database->prepare("
            SELECT
                vr.capability_id,
                vr.capability_name,
                vr.capability_max_value,
                vr.indicator_id,
                vr.indicator_name,
                vr.assessment_id,
                vr.assessment_title,
                vr.capability_mark_type_id AS rubric_capability_mark_type_id,
                vr.assessment_mark_type AS mark_type,
                vr.indicator_max_value AS max_value
            FROM v_rubric vr
            JOIN assessment a ON vr.assessment_id = a.id
            WHERE {$where}
            AND a.deleted_at IS NULL
            ORDER BY a.due_date, vr.capability_sequence, vr.indicator_sequence, a.id;
        ");

        $capabilityStatement->execute($pb->getParams());


        // Collate the selected descriptor values into longitudinal array
        $longitude      = [];
        $assessmentList = [];

        while ($row = $capabilityStatement->fetch(\PDO::FETCH_ASSOC)) {
            $longitude[$row['capability_id']]['name']                                                                  = $row['capability_name'];
            $longitude[$row['capability_id']]['mark_type'][$row['assessment_id']]                                      = $row['mark_type'];
            $longitude[$row['capability_id']]['max_value'][$row['assessment_id']]                                      = $row['capability_max_value'];
            $longitude[$row['capability_id']]['indicators'][$row['indicator_id']]['name']                              = $row['indicator_name'];
            $longitude[$row['capability_id']]['indicators'][$row['indicator_id']]['activities'][$row['assessment_id']] = [
                'name'                           => $row['assessment_title'],
                'rubric_capability_mark_type_id' => $row['rubric_capability_mark_type_id'],
                'max_value'                      => $row['max_value'],
            ];

            $assessmentList[$row['assessment_id']]['name']                           = $row['assessment_title'];
            $assessmentList[$row['assessment_id']]['rubric_capability_mark_type_id'] = $row['rubric_capability_mark_type_id'];
        }

        // Sort by name
        foreach ($longitude as &$capability) {
            uasort($capability['indicators'], [$this, 'sortByName']);
        }

        unset($capability);
        uasort($longitude, [$this, 'sortByName']);

        // Add assessment info to the list
        $longitude['assessments'] = $assessmentList;

        if (count($feedbackIds) > 0) {
            // Find the descriptors selected for each of these feedback IDs
            $pb = new Builder($this->database->getQuoter(), '');
            $pb->in('sr.id', $feedbackIds);

            $where = implode(' AND ', $pb->getWhere());
            $averageStatement = $this->database->prepare("
                SELECT
                    rc.id AS capability_id,
                    ri.id AS indicator_id,
                    a.id AS assessment_id,
                    a.due_date,
                    rim.value,
                    rcm.value AS capability_value,
                    rcm.human_readable_value AS capability_human_readable_value,
                    rd.description,
                    rl.name AS level
                FROM rubric_capability_mark rcm
                INNER JOIN rubric_capability      rc ON rcm.rubric_capability_id = rc.id
                INNER JOIN rubric_indicator       ri ON ri.rubric_capability_id = rc.id
                INNER JOIN submission_return      sr ON rcm.return_id = sr.id
                INNER JOIN submission_box         sb ON sr.box_id = sb.id
                -- Join rubric_indicator_usage to remove indicators indicators that aren't part of the submission
                INNER JOIN rubric_indicator_usage riu ON riu.rubric_indicator_id = ri.id AND riu.submission_box_id = sb.id
                INNER JOIN assessment             a ON sb.assessment_id = a.id
                -- Check for rubric_indicator_mark_id match to avoid duplicate results after the join
                LEFT JOIN v_rubric_indicator_mark_extend  rim ON rcm.id = rim.rubric_capability_mark_id AND rim.rubric_indicator_id = ri.id
                LEFT JOIN rubric_descriptor       rd ON rim.descriptor_id = rd.id
                LEFT JOIN rubric_level            rl ON rd.level_id = rl.id
                WHERE {$where}
                AND rc.deleted_at IS NULL
                AND ri.deleted_at IS NULL
                AND sr.deleted_at IS NULL
                AND a.deleted_at IS NULL
                ORDER BY a.due_date ASC;
            ");

            $averageStatement->execute($pb->getParams());

            while ($row = $averageStatement->fetch(\PDO::FETCH_ASSOC)) {
                if (
                    !isset($longitude[$row['capability_id']]['mark'][$row['assessment_id']])
                    && $longitude[$row['capability_id']]['max_value'][$row['assessment_id']] > 0
                    && $row['capability_value'] !== null
                    && isset($dueWorks[$row['assessment_id']])
                ) {
                    $longitude[$row['capability_id']]['mark'][$row['assessment_id']] = $row['capability_human_readable_value'];
                }

                $longitude[$row['capability_id']]['indicators'][$row['indicator_id']]['activities'][$row['assessment_id']]['descriptor'] = $row['description'];
                $longitude[$row['capability_id']]['indicators'][$row['indicator_id']]['activities'][$row['assessment_id']]['level']      = $row['level'];
                $longitude[$row['capability_id']]['indicators'][$row['indicator_id']]['activities'][$row['assessment_id']]['value']      = $row['value'];
            }
        }

        unset($pb);

        // Return immediately if we do not required comparison data for box and whisker
        if (!$includeComparison) {
            return $longitude;
        }

        $allResults = [];

        if (count($allFeedbackIds) > 0) {
            //Find all students results across all indicators in all activities
            $pb = new Builder($this->database->getQuoter(), '');
            $pb->in('sr.id', $allFeedbackIds);

            $where = implode(' AND ', $pb->getWhere());
            $averageStatement = $this->database->prepare("
                SELECT
                    rc.id AS capability_id,
                    ri.id AS indicator_id,
                    a.id AS assessment_id,
                    a.due_date,
                    rim.value
                FROM       rubric_indicator_mark  rim
                INNER JOIN rubric_capability_mark rcm ON rim.rubric_capability_mark_id = rcm.id
                INNER JOIN rubric_capability       rc ON rcm.rubric_capability_id = rc.id
                INNER JOIN rubric_descriptor       rd ON rim.descriptor_id = rd.id
                INNER JOIN rubric_indicator        ri ON rd.rubric_indicator_id = ri.id
                INNER JOIN submission_return       sr ON rcm.return_id = sr.id
                INNER JOIN submission_box          sb ON sr.box_id = sb.id
                INNER JOIN assessment               a ON sb.assessment_id = a.id
                WHERE {$where}
                AND rc.deleted_at IS NULL
                AND ri.deleted_at IS NULL
                AND sr.deleted_at IS NULL
                AND a.deleted_at IS NULL
                ORDER BY a.due_date ASC;
            ");

            $averageStatement->execute($pb->getParams());

            while ($row = $averageStatement->fetch(\PDO::FETCH_ASSOC)) {
                $allResults[$row['assessment_id']][$row['capability_id']][$row['indicator_id']][] = (float)$row['value'];
            }
        }

        //Insert the statistical summary data collated from all students results across all indicators in all activities
        foreach ($allResults as $assessmentId => $assessment) {
            if (!key_exists($assessmentId, $longitude['assessments'])) {
                continue;
            }

            foreach ($assessment as $capabilityId => $capability) {
                if (!isset($longitude[$capabilityId])) {
                    continue;
                }

                foreach ($capability as $indicatorId => $indicator) {
                    $longitude[$capabilityId]['indicators'][$indicatorId]['activities'][$assessmentId]['statistics'] = (new Summary($indicator))->getSummary();
                }
            }
        }

        return $longitude;
    }


    /**
     * Copies a Rubric from source DueWork to destination DueWork.
     *
     * Overall logic is to build the source rubric object graph, then clone and clear all IDs.
     *
     * If during traversal we detect that the destination folder already has a indicator with the same
     * name, we use the destination indicator ID to avoid insertion of a duplicate.
     *
     * Logically:
     * * Levels are copied from srcBox to dstBox
     * * Descriptors are copied from srcBox to dstBox
     * * Indicator only copied from src folder to dst folder (if not already present on dst)
     * * Usages are copied from srcBox + srcIndicator to dstBox + dstIndicator
     *
     * @param int $sourceAssessmentId
     * @param int $destinationAssessmentId
     * @param AssessmentContext $destinationContext
     * @return bool Whether a new Rubric was created.
     * @throws MapperException
     * @throws Deleted
     */
    public function copyRubric(int $sourceAssessmentId, int $destinationAssessmentId, AssessmentContext $destinationContext): bool
    {
        // TODO extract this entire monstrosity elsewhere

        // Get required mappers
        $boxMapper = SubmissionBoxMapper::getInstance();

        $sourceBox = $boxMapper->findOneByColumn('assessment_id', $sourceAssessmentId);

        /** @var GenericCollection<LevelModel> $srcLevels */
        $srcLevels = $this->levelMapper->findByColumn('box_id', $sourceBox->getId())->indexBy('id');

        /* TODO Iterate through a set of assocaited grids
         *  An assessment can have multiple grids associated with it; you'll have to clone each one-by-one, making
         *  this into a gigantic loop
         */
//        if (! $sourceBox->getUseRubric()) {
//            // No rubric? Can't work with that - die.
//            return false;
//        }

        $dstBox = $boxMapper->findOneByColumn('assessment_id', $destinationAssessmentId);
        $dstBox->getAssessmentModel()->setContextFolder($destinationContext->getFolder());

        $dstFolder = $dstBox->getAssessmentModel()->getContextFolder();

        /* Set up a stub rubric instance for the destination submission box
         */
        $destinationRubricInstance = $this->rubricInstanceMapper->createModel()
            ->setAssessmentModel($dstBox->getAssessmentModel());

        $this->rubricInstanceMapper->save($destinationRubricInstance);

        // Cannot mutate identity of objects returned from mappers.
        // Index all these collections by source version ID, even though the object will be destination version.
        /** @var GenericCollection<LevelModel> $dstLevels */
        $dstLevels = $this->levelMapper->createCollection();

        /** @var GenericCollection<DescriptorModel> $dstDescriptors */
        $dstDescriptors = $this->descriptorMapper->createCollection();

        /** @var GenericCollection<CapabilityModel> $dstCapabilities */
        $dstCapabilities = $this->capabilityMapper->createCollection();

        /** @var GenericCollection<IndicatorModel> $dstIndicators */
        $dstIndicators = $this->indicatorMapper->createCollection();

        /** @var GenericCollection<CapabilityUsageModel> $dstCapabilityUsages */
        $dstCapabilityUsages = $this->capabilityUsageMapper->createCollection();

        /** @var GenericCollection<IndicatorUsageModel> $dstIndicatorUsages */
        $dstIndicatorUsages = $this->indicatorUsageMapper->createCollection();

        // Copy Levels
        foreach ($srcLevels as $srcLevel) {
            $dstLevel = clone $srcLevel;

            $dstLevel->setId(null)
                ->setRubricInstance($destinationRubricInstance)
                ->setDescriptors($this->descriptorMapper->createCollection());

            $dstLevels->setOffset($srcLevel->getId(), $dstLevel);
        }

        // Get Source Descriptors
        /** @var GenericCollection<DescriptorModel> $srcDescriptors */
        $srcDescriptors = $this->descriptorMapper->findByColumn('level_id', $srcLevels->getPropertyValues('id'))->indexBy('id');

        if ($srcDescriptors->count() === 0) {
            // no descriptors are set for source assessment
            return false;
        }

        $srcIndicators   = $this->indicatorMapper->createCollection();
        $srcCapabilities = $this->capabilityMapper->createCollection();

        /* Get existing destination indicators.
         *
         * If during destination object graph build, we detect that a source descriptor
         * already exists on destination, use the destination version instead of creating a new indicator
         */
        /** @var DescriptorModel $firstSrcDescriptor */
        $firstSrcDescriptor = $srcDescriptors->first();
        $parentType         = $firstSrcDescriptor->getIndicator()->getCapability()->getParentType();

        $existingIndicators = $this->indicatorMapper->findForParent(
            $dstFolder->getId(),
            $parentType,
        );

        // If the rubric is being duped into a unit spec:
        // also fetch the list of course-level capabilities and indicators
        $unitSpecs = $this->descriptorMapper->findByColumn('folder_id', $dstFolder->getId());

        /** @var UnitSpecification|null $unitSpec */
        $unitSpec = $unitSpecs->first();

        if ($unitSpec) {
            $existingIndicators = $existingIndicators->merge(
                $this->indicatorMapper->findForParent(
                    $unitSpec->getFolder()->getParent()->getId(),
                    $parentType,
                )
            );
        }

        // Convert to 2-D arrays, indexed by 'capability' then 'name', so we can later find duplicates.
        /** @var IndicatorModel[][] $existingIndicatorMap */
        $existingIndicatorMap = [];

        /** @var CapabilityModel[] $existingCapabilityMap */
        $existingCapabilityMap = [];

        foreach ($existingIndicators as $existingIndicator) {
            $capabilityName                                        = Util::normalizeName($existingIndicator->getCapability()->getName());
            $indicatorName                                         = Util::normalizeName($existingIndicator->getName());
            $existingIndicatorMap[$capabilityName][$indicatorName] = $existingIndicator;
            $existingCapabilityMap[$capabilityName]                = $existingIndicator->getCapability();
        }

        // Build new Rubric for destination box
        foreach ($srcDescriptors as $srcDescriptor) {
            // Copy source capability, or reuse existing
            $srcCapabilityId = $srcDescriptor->getIndicator()->getCapability()->getId();

            if (!$srcCapabilities->getOffset($srcCapabilityId)) {
                $srcCapability = $srcDescriptor->getIndicator()->getCapability();
                $srcCapabilities->setOffset($srcCapabilityId, $srcCapability);
            }

            // Copy source indicator or re-use existing indicator
            $srcIndicatorId = $srcDescriptor->getIndicator()->getId();

            if (!$srcIndicators->getOffset($srcIndicatorId)) {
                $srcIndicator = $srcDescriptor->getIndicator();
                $srcIndicators->setOffset($srcIndicatorId, $srcIndicator);
            }

            // Does this indicator already exist on destination folder ?
            $duplicateCapability = false;
            $duplicateIndicator  = false;
            $capabilityName      = Util::normalizeName($srcCapabilities->getOffset($srcCapabilityId)->getName());
            $indicatorName       = Util::normalizeName($srcIndicators->getOffset($srcIndicatorId)->getName());

            if (array_key_exists($capabilityName, $existingCapabilityMap)) {
                $duplicateCapability = true;

                if (array_key_exists($indicatorName, $existingIndicatorMap[$capabilityName])) {
                    $duplicateIndicator = true;
                }
            }

            // Did we find an existing capability with the same name?
            if ($duplicateCapability) {
                $dstCapability = $existingCapabilityMap[$capabilityName];
            } else {
                // hydrate to get curriculum nodes
                $srcCapability = $this->capabilityMapper->findById($srcDescriptor->getIndicator()->getCapability()->getId());

                $dstCapability = clone $srcCapability;

                $dstCapability->setId(null)
                    ->setParentId($dstFolder->getId());

                $existingCapabilityMap[$capabilityName] = $dstCapability;
            }

            $dstCapabilities->setOffset($srcCapabilityId, $dstCapability);

            // Did we find an existing indicator with the same name?
            if ($duplicateIndicator) {
                $dstIndicator = $existingIndicatorMap[$capabilityName][$indicatorName];
            } else {
                // hydrate to get curriculum nodes
                $srcIndicator = $this->indicatorMapper->findById($srcDescriptor->getIndicator()->getId());

                $dstIndicator = clone $srcIndicator;

                $dstIndicator->setId(null)
                    ->setCapability($dstCapability);

                $existingIndicatorMap[$capabilityName][$indicatorName] = $dstIndicator;
            }

            $dstIndicators->setOffset($srcIndicatorId, $dstIndicator);

            // Descriptor
            $dstDescriptor = clone $srcDescriptor;

            $dstDescriptor->setId(null);
            $dstDescriptors->setOffset($srcDescriptor->getId(), $dstDescriptor);

            // Link Descriptor + Level
            /** @var LevelModel $dstLevel */
            $dstLevel = $dstLevels->getOffset($srcDescriptor->getLevel()->getId());
            $dstDescriptor->setLevel($dstLevel);

            $dstLevel->getDescriptors()->setOffset($srcDescriptor->getId(), $dstDescriptor);

            // Link Descriptor + indicator
            $dstDescriptor->setIndicator($dstIndicators->getOffset($srcIndicatorId));
        }

        // Get Source Usages
        /** @var GenericCollection<CapabilityUsageModel> $srcCapabilityUsages */
        $srcCapabilityUsages = $this->capabilityUsageMapper->findByColumnList([
            'rubric_capability_id' => $srcCapabilities->getPropertyValues('id'),
            'submission_box_id'    => $sourceBox->getId(),
        ]);

        /** @var GenericCollection<IndicatorUsageModel> $srcIndicatorUsages */
        $srcIndicatorUsages = $this->indicatorUsageMapper->findByColumnList([
            'rubric_indicator_id' => $srcIndicators->getPropertyValues('id'),
            'submission_box_id'   => $sourceBox->getId(),
        ]);

        // Copy capability Usages
        foreach ($srcCapabilityUsages as $srcCapabilityUsage) {
            /** @var CapabilityModel $dstCapability */
            $dstCapability = $dstCapabilities->getOffset($srcCapabilityUsage->getCapability()->getId());

            $dstCapabilityUsage = clone $srcCapabilityUsage;

            $dstCapabilityUsage->setCapability($dstCapability)
                ->setRubricInstance($destinationRubricInstance);

            $dstCapabilityUsages->add($dstCapabilityUsage);
        }

        // Copy indicator usages
        foreach ($srcIndicatorUsages as $srcIndicatorUsage) {
            /** @var IndicatorModel $dstIndicator */
            $dstIndicator = $dstIndicators->getOffset($srcIndicatorUsage->getIndicator()->getId());

            $dstIndicatorUsage = clone $srcIndicatorUsage;

            $dstIndicatorUsage->setIndicator($dstIndicator)
                ->setRubricInstance($destinationRubricInstance);

            $dstIndicatorUsages->add($dstIndicatorUsage);
        }

        // Save (order is important to ensure IDs are assigned to parents b4 saving children)
        $this->levelMapper->save($dstLevels);
        $this->capabilityMapper->save($dstCapabilities);
        $this->indicatorMapper->save($dstIndicators);
        $this->descriptorMapper->save($dstDescriptors);
        $this->capabilityUsageMapper->save($dstCapabilityUsages);
        $this->indicatorUsageMapper->save($dstIndicatorUsages);

        return true;
    }

    /**
     * Deletes all traces of a rubric from the due work identified by `$assessmentId`.
     *
     * This includes:
     *     - capability and indicator usages
     *     - levels
     *     - descriptors on levels
     *
     * @param int $assessmentId
     * @return void
     * @throws Deleted
     * @throws MapperException
     */
    public function deleteRubric(int $assessmentId): void
    {
        $boxMapper = SubmissionBoxMapper::getInstance();
        $box = $boxMapper->findOneByColumn('assessment_id', $assessmentId);

        /* TODO Replace with a grid identifier (rubric instance id).
         *  You can probably provide the instance identifier instead of $assessmentId, but it might be worthwhile to
         *  have both, so you can check that rubric_instance_id is indeed related to $assessmentId as a safety assertion
         */
        $this->capabilityUsageMapper->deleteByColumn('submission_box_id', $box->getId());
        $this->indicatorUsageMapper->deleteByColumn('submission_box_id', $box->getId());

        $levels = $this->levelMapper->findByColumn('box_id', $box->getId());
        $this->levelMapper->delete($levels);

        $this->descriptorMapper->deleteByColumn('level_id', $levels->getPropertyValues('id'));
    }


    /**
     * Returns normalised marks for all student rubric marks in specified folders
     * on assessments due in the specified term.
     *
     * Does not respect assessment.weight, ie all assessments are weighted at 1.
     *
     * If a student mark has a NULL value, it is ignored for aggregation.
     *
     * Normalised mark: (0.0 - 100.0)
     *
     * TODO this is copied wholesale from now-deleted `Duework_Rubric_Handler`; consider modernizing
     *
     * @param int[]     $folderIds
     * @param TermModel $term
     * @return array
     * @internal  string       $data [sb.id][rc.id]['grouping'] indicator.grouping
     * @internal  string       $data [sb.id][rc.id]['name'] indicator.name
     * @internal  string       $data [sb.id][rc.id]['max_mark'] max mark
     * @internal  array        $data [sb.id][rc.id]['marks']
     * @internal  float        $data [sb.id][rc.id]['marks'][student.id]
     * @throws UnderflowException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getStudentMarksByIndicatorInFolder(array $folderIds, TermModel $term)
    {
        if (!count($folderIds)) {
            return [];
        }

        // Step 1: figure out the due works in the term for the given folders
        $pb = new Builder($this->database->getQuoter(), '');
        $pb->in('rc.parent_id', $folderIds)
            /* TODO This parent_type never varies. Why did options exist for it, but were never used?
             */
           ->equals('rc.parent_type', CapabilityModel::PARENT_TYPE_CATEGORY)
           ->greaterThanOrEqualTo('a.due_date', $term->getStart()->getDatabase())
           ->equals('sr.review_route', SubmissionBox\ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
           ->isNull('a.deleted_at')
           ->notNull('sr.published_at')
           ->isNull('sr.deleted_at')
           ->isNull('u.deleted_at');

        $finish = $term->getActualFinish();

        if (null !== $finish) {
            $pb->lessThan('a.due_date', $finish->getDatabase());
        }

        $where = implode(' AND ', $pb->getWhere());

        $assessmentStatement = $this->database->prepare("
            SELECT
                sr.owner AS studentId,
                sr.id    AS feedbackId,
                a.id     AS assessmentId
            FROM submission_return sr
            INNER JOIN user                      u ON sr.owner = u.id
            INNER JOIN submission_box           sb ON sr.box_id = sb.id
            INNER JOIN rubric_capability_usage rcu ON sb.id = rcu.submission_box_id
            INNER JOIN rubric_capability        rc ON rgu.rubric_capability_id = rc.id
            INNER JOIN assessment                a ON sb.assessment_id = a.id
            WHERE {$where}
            ORDER BY sr.published_at
        ");

        $assessmentStatement->execute($pb->getParams());

        $groupedFeedbackIds = [];

        foreach ($assessmentStatement as $row) {
            $groupedFeedbackIds[$row['assessmentId']][$row['studentId']] = $row['feedbackId'];
        }

        // We don't care about the assessment ID; only that the feedback is the
        // most recent for that assessment.
        $feedbackIds = [];

        foreach ($groupedFeedbackIds as $assessmentId => $feedbackIdsByStudent) {
            $feedbackIds = array_merge($feedbackIds, $feedbackIdsByStudent);
        }

        // Short-circuit if there's no feedback
        if (empty($feedbackIds)) {
            return [];
        }

        sort($feedbackIds);

        // Step 2: Get max mark per indicator and dueWork for each due work
        $results = [];

        $pb = new Builder($this->database->getQuoter(), '');
        $pb->in('rc.parent_id', $folderIds)
           ->equals('rc.parent_type', CapabilityModel::PARENT_TYPE_CATEGORY)
           ->notNull('riu.max_value')
           ->greaterThan('riu.max_value', 0.0);

        $where = implode(' AND ', $pb->getWhere());

        $maxMarksStatement = $this->database->prepare("
            SELECT
                a.id,
                ri.id AS criterionId,
                rc.name AS grouping,
                ri.name,
                riu.max_value AS maxMark
            FROM rubric_indicator        ri
            JOIN rubric_capability       rc ON rc.id = ri.rubric_capability_id AND rc.deleted_at IS NULL
            JOIN rubric_indicator_usage riu ON ri.id = riu.rubric_indicator_id
            JOIN submission_box          sb ON riu.submission_box_id = sb.id
            JOIN assessment               a ON sb.assessment_id = a.id
            WHERE {$where}
            ORDER BY riu.submission_box_id, ri.id
        ");

        $maxMarksStatement->execute($pb->getParams());

        foreach ($maxMarksStatement as $row) {
            $boxId       = $row['id'];
            $indicatorId = $row['criterionId'];

            $results[$boxId][$indicatorId] = [
                'grouping' => $row['grouping'],
                'name'     => $row['name'],
                'maxMark'  => $row['maxMark'],
                'marks'    => [],
            ];
        }

        // Step 3: pass to getLongitudinal or similar?
        $pb = new Builder($this->database->getQuoter(), '');
        $pb->in('sr.id', $feedbackIds);

        $where = implode(' AND ', $pb->getWhere());

        /* Note that the old aliases still exist here: conceptually, grouping === capability
         * ...but it doesn't even get used, so it seems to be a diagnostic thing
         */
        $descriptorStatement = $this->database->prepare("
            SELECT
                sr.owner AS studentId,
                ri.id    AS indicatorId,
                rc.name  AS grouping,
                ri.name,
                a.id,
                a.title,
                rd.description,
                rim.value
            FROM       rubric_indicator_mark  rim
            INNER JOIN rubric_capability_mark rcm ON rim.rubric_capability_mark_id = rcm.id
            INNER JOIN rubric_capability       rc ON rcm.rubric_capability_id = rc.id
            INNER JOIN rubric_descriptor       rd ON rim.descriptor_id = rd.id
            INNER JOIN rubric_indicator        ri ON rd.rubric_indicator_id = ri.id
            INNER JOIN submission_return       sr ON rcm.return_id = sr.id
            INNER JOIN submission_box          sb ON sr.box_id = sb.id
            INNER JOIN assessment               a ON sb.assessment_id = a.id
            WHERE {$where}
            ORDER BY rc.name, ri.name, a.due_date DESC;
        ");

        $descriptorStatement->execute($pb->getParams());

        // Step 4: smoosh them all together
        foreach ($descriptorStatement as $row) {
            $boxId       = $row['id'];
            $indicatorId = $row['indicatorId'];
            $mark        = $row['value'];
            $maxMark     = $results[$boxId][$indicatorId]['maxMark'] ?? 0;

            if ($maxMark === 0) {
                continue;
            }

            $results[$boxId][$indicatorId]['marks'][$row['studentId']] = 100.0 * $mark / $maxMark;
        }

        // Finally:
        return $results;
    }


    /**
     * Get all indicators available on the specified parent
     *
     * TODO this is copied wholesale from now-deleted `Duework_Rubric_Handler`; consider modernizing
     *
     * @param int[]  $parentIds
     * @param string $parentType
     *
     * @return array
     * @var string   $indicators[rc.id]['grouping']
     * @var string   $indicators[rc.id]['name']
     */
    public function getAvailableIndicators(array $parentIds, $parentType = CapabilityModel::PARENT_TYPE_CATEGORY): array
    {
        if (!count($parentIds)) {
            return [];
        }

        $pb = new Builder($this->database->getQuoter(), '');
        $pb->in('rc.parent_id', $parentIds)
           ->equals('rc.parent_type', $parentType);

        $whereClause = implode(' AND ', $pb->getWhere());

        $stmt = $this->database->prepare("
            SELECT
                rc.name AS grouping,
                ri.id, ri.name
            FROM rubric_indicator ri
            JOIN rubric_capability rc ON rc.id = ri.rubric_capability_id AND rc.deleted_at IS NULL
            WHERE {$whereClause}
            AND ri.deleted_at IS NULL
            AND rc.deleted_at IS NULL
            ORDER BY rc.name, ri.name
        ");

        $stmt->execute($pb->getParams());

        $indicators = [];

        /* Index indicators by their (indicatorId, [indicator data]) tuple
         */
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $indicators[$row['id']] = [
                'grouping' => $row['grouping'],
                'name'     => $row['name'],
            ];
        }

        return $indicators;
    }


    /**
     * Gets feedback ids for the due works which uses rubric
     * @param GenericCollection $dueWorks
     * @param User              $student
     * @param bool              $includeComparison
     * @return array
     */
    private function getFeedbackIdsWithRubric(GenericCollection $dueWorks, User $student, bool $includeComparison): array
    {
        $pb = new Builder($this->database->getQuoter(), '');
        $pb->in('a.id', $dueWorks->getPropertyValues('id'))
           ->isNull('a.deleted_at')
           ->isTrue('sb.use_rubric')
           ->equals('sr.review_route', SubmissionBox\ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
           ->notNull('sr.published_at')
           ->isNull('sr.deleted_at');

        if (!$includeComparison) {
            $pb->equals('sr.owner', $student->getId());
        }

        $where = implode(' AND ', $pb->getWhere());

        /* TODO tis submission_return idea must go away here
         */
        $feedbackStatement = $this->database->prepare("
            SELECT
                sr.id    AS feedbackId,
                a.id     AS assessmentId,
                sr.owner AS studentUserId
            FROM submission_return sr
            INNER JOIN submission_box sb ON sr.box_id = sb.id
            INNER JOIN assessment      a ON sb.assessment_id = a.id
            WHERE {$where}
            ORDER BY sr.published_at ASC
        ");

        $feedbackStatement->execute($pb->getParams());

        $allFeedbackIds     = [];
        $studentFeedbackIds = [];

        foreach ($feedbackStatement as $row) {
            if ((int)$row['studentUserId'] === $student->getId()) {
                $studentFeedbackIds[$row['assessmentId']] = $row['feedbackId'];
            }

            if ($includeComparison) {
                $allFeedbackIds[] = $row['feedbackId'];
            }
        }

        return [$studentFeedbackIds, $allFeedbackIds];
    }


    /**
     * Quick alpha sort function for indicators and capabilities summary section
     * (this method duped from the Rubric Markbook Controller)
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    private function sortByName(array $a, array $b): int
    {
        return strnatcasecmp($a['name'], $b['name']);
    }
}
