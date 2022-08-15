<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel;

use Alaress\Database\Predicate\Builder;
use Database;
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
use Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark;
use Schoolbox\Assessment\Rubric\MarkedGrid\IndicatorMark;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\Assessment\Rubric\RowSet\Capability;
use Schoolbox\Assessment\Rubric\RowSet\Indicator;
use Schoolbox\Assessment\Rubric\RowSet\RowSet;
use Schoolbox\Assessment\Rubric\RowSet\ValidRowSet;
use Schoolbox\Assessment\Rubric\Util;
use Schoolbox\Assessment\Term\Model as TermModel;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\IMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Analytics\BoxPlot\Summary;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedbackModel;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Mapper\Exception as MapperException;
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
     * Stores the singleton instance of the mapper
     *
     * @var self[]
     */
    protected static $instance;

    /**
     * Retrieves the singleton instance of this class.
     *
     * @return static
     */
    public static function getInstance()
    {
        $class_name = get_called_class();
        if (!isset(self::$instance[$class_name])) {
            self::$instance[$class_name] = new static();
        }

        return self::$instance[$class_name];
    }

    /** @var Database */
    protected $database;

    /** */
    public function __construct()
    {
        $this->database = Database::getInstance();
    }


    /**
     * Retrieves a rowset for the given context.
     *
     * @todo consider whether this belongs here, or in some other class
     *
     * @param AssessmentContext $context
     * @param bool              $getUsages
     * @param bool              $useCache If false, bypass cache for fetch of
     *                                    capabilities+indicators
     * @return RowSet
     * @throws MapperException
     */
    public function retrieveRowSet(AssessmentContext $context, bool $getUsages, bool $useCache = true): RowSet
    {
        $rowSet = new RowSet($context);

        // Load the capabilities and indicators for this context
        $folder           = $context->getBaseContext()->getFolder();
        $capabilityModels = CapabilityMapper::getInstance()->findForFolder($folder->getId(), $useCache);
        $indicatorModels  = IndicatorMapper::getInstance()->findForFolder($folder->getId(), $useCache);

        // Unit-specs have their course spec's capabilities/indicators as well
        $courseSpecFolderId = null;
        if ($context instanceof UnitSpecification || $folder->hasUnitSpecification()) {
            $courseSpecFolderId = (int)$folder->getParent()->getId();
            $capabilityModels   = CapabilityMapper::getInstance()
                                                  ->findForFolder($courseSpecFolderId, $useCache)
                                                  ->merge($capabilityModels);
            $indicatorModels    = IndicatorMapper::getInstance()
                                                 ->findForFolder($courseSpecFolderId, $useCache)
                                                 ->merge($indicatorModels);
        }


        /** @var GenericCollection|\Schoolbox\Assessment\Rubric\RowSet\Capability[] $capabilities */
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
                    ->setCurriculumNodes($capabilityModel->getCurriculumNodes());

                if ($getUsages) {
                    foreach (IndicatorUsageMapper::getInstance()->findCapabilityUsage($capabilityId) as $capabilityUsage) {
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
                ->setCurriculumNodes($indicatorModel->getCurriculumNodes());

            if ($getUsages) {
                foreach (IndicatorUsageMapper::getInstance()->findIndicatorUsage($indicatorId) as $indicatorUsage) {
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

        // Required mappers
        $capabilityMapper         = CapabilityMapper::getInstance();
        $indicatorMapper          = IndicatorMapper::getInstance();

        // Existing data
        $existingRowSet           = $this->retrieveRowSet($rowSet->getContext(), true);
        $existingCapabilityIds    = $existingRowSet->retrieveCapabilityIds();
        $existingIndicatorIds     = $existingRowSet->retrieveIndicatorIds();
        $existingCapabilityModels = $capabilityMapper->findByColumn('id', $existingCapabilityIds);
        $existingIndicatorModels  = $indicatorMapper->findByColumn('id', $existingIndicatorIds);

        // New data
        $newCapabilityIds         = $rowSet->retrieveCapabilityIds();
        $newIndicatorIds          = $rowSet->retrieveIndicatorIds();
        $newCapabilityModels      = $capabilityMapper->createCollection();
        $newIndicatorModels       = $indicatorMapper->createCollection();
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
                $capabilityModel = (new CapabilityModel())
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
                    $indicatorModel = $indicatorMapper->createModel()
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
            UnitSpecificationMapper::getInstance(),
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
        $capabilityMapper->save($newCapabilityModels);
        $indicatorMapper->save($newIndicatorModels);


        // Tidy up unsaved capabilities+indicators
        $this->tidyUp($indicatorMapper, ['id' => $deletedIndicatorIds], []);
        $this->tidyUp($capabilityMapper, ['id' => $deletedCapabilityIds], []);
    }


    /**
     * Retrieve a grid for the given assessment.
     *
     * @param DueWorkModel $assessment
     * @param bool         $getUsages
     * @return Grid
     * @throws MapperException
     */
    public function retrieveGrid(DueWorkModel $assessment, bool $getUsages): Grid
    {
        // Build the rowset first
        $grid = new Grid($assessment, $this->retrieveRowSet($assessment->getContext(), $getUsages));

        // Assessment isn't persisted yet? This has an empty grid
        if (!$assessment->getId()) {
            return $grid;
        }

        // Load the levels, selected capabilities+indicators, descriptors for
        // the assessment
        $capabilityUsageModels = CapabilityUsageMapper::getInstance()->findByColumn(
            'sb.assessment_id',
            $assessment->getId(),
            ['order' => ['sequence' => 'asc',]]
        )->indexByCallback(function (CapabilityUsageModel $gu) {
            return $gu->getCapability()->getId();
        });
        $indicatorUsageModels = IndicatorUsageMapper::getInstance()->findByColumn(
            'sb.assessment_id',
            $assessment->getId(),
            ['order' => ['sequence' => 'asc',]]
        )->indexByCallback(function (IndicatorUsageModel $cu) {
            return $cu->getIndicator()->getId();
        });
        $levelModels = LevelMapper::getInstance()->findByColumn(
            'b.assessment_id',
            $assessment->getId(),
            ['order' => ['sequence' => 'asc',]]
        )->indexByCallback(function (LevelModel $l) {
            return $l->getId();
        });
        $descriptorModels = DescriptorMapper::getInstance()->findByColumnList([
            'rubric_indicator_id' => $indicatorUsageModels->keys(),
            'level_id'            => $levelModels->keys(),
        ]);

        $groupedIndicatorUsageModels = $indicatorUsageModels->groupByCallback(
            function (IndicatorUsageModel $cu) {
                return $cu->getIndicator()->getCapability()->getId();
            }
        );


        // Create descriptor models
        $descriptorsByLevel     = [];
        $descriptorsByIndicator = [];
        foreach ($descriptorModels as $descriptorModel) {
            $levelId     = $descriptorModel->getLevel()->getId();
            $indicatorId = $descriptorModel->getIndicator()->getId();
            $descriptor  = (new \Schoolbox\Assessment\Rubric\Grid\Descriptor())
                ->setId($descriptorModel->getId())
                ->setDescription($descriptorModel->getDescription())
                ->setMaxValue($descriptorModel->getMaxValue());

            $descriptorsByIndicator[$indicatorId][$levelId] = $descriptor;
            $descriptorsByLevel[$levelId][$indicatorId]     = $descriptor;
        }


        // Add levels
        foreach ($levelModels as $levelModel) {
            $levelId = $levelModel->getId();
            $level   = (new \Schoolbox\Assessment\Rubric\Grid\Level())
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
                $assessment->isMarkPerCapability()
                    ? $capabilityUsageModel->getMaxValue()
                    : $capabilityUsage->calculateMaxValueFromIndicators()
            );

            $grid->getCapabilityUsages()[$capabilityId] = $capabilityUsage;
        }

        return $grid;
    }


    /**
     * Saves this grid to the database.
     *
     * @param Grid      $grid
     * @param null|Grid $originalGrid If set, this is the state of the
     *                                assessment's grid pre-save
     * @throws \UnexpectedValueException If a duplicate indicator or capability exists in the folder
     * @throws \UnexpectedValueException If course capability duplicates a unit capability
     * @throws \UnexpectedValueException If a capability or indicator that should not be deleted has been
     * @throws \InvalidArgumentException If an undocumented error occurred whilst persisting
     */
    public function persistGrid(Grid $grid, ?Grid $originalGrid): void
    {
        // The mappers still deal in submission boxes, but we deal in levels
        // If this exceptioned out, it was never meant to be
        $submissionBox = SubmissionBoxMapper::getInstance()->findOneByColumn(
            'assessment_id',
            $grid->getAssessment()->getId()
        );

        // TODO some way to wrap all this in a transaction would be nice... -_-;
        // (The answer exists: merge SA-6405)
        $levelMapper      = LevelMapper::getInstance();
        $descriptorMapper = DescriptorMapper::getInstance();
        $indicatorMapper  = IndicatorMapper::getInstance();
        $capabilityMapper = CapabilityMapper::getInstance();


        // First, persist the levels
        $levelModels   = $levelMapper->createCollection();
        $levelSequence = 1;
        foreach ($grid->getLevels() as $level) {
            $levelModel = $levelMapper->createModel()
                ->setId($level->getId())
                ->setName($level->getName())
                ->setSubmissionBox($submissionBox)
                ->setSequence($levelSequence++);
            $levelModels->add($levelModel);

            // Need to save NOW so that I have an ID which can then be assoc'd
            // with a descriptor
            $levelMapper->save($levelModel);
            $level->setId($levelModel->getId());
        }


        // Persist capabilities+indicators
        $indicatorModels  = $indicatorMapper->createCollection();
        $capabilityModels = $capabilityMapper->createCollection();
        foreach ($grid->getRowSet()->getCapabilities() as $capability) {
            $capabilityId = $capability->getId();
            if (!$capabilityId) {
                continue;
            }
            $capabilityModel = $capabilityMapper->findById($capabilityId)
                ->setName($capability->getName());
            $capabilityModels[$capabilityId] = $capabilityModel;

            foreach ($capability->getIndicators() as $indicator) {
                $indicatorId = $indicator->getId();
                if (!$indicatorId) {
                    continue;
                }
                $indicatorModel                = $indicatorMapper->findById($indicatorId)
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
            UnitSpecificationMapper::getInstance(),
            $this,
            $originalGrid ? $originalGrid->getRowSet() : null,
            false,
            $grid->getAssessment()
        );


        // `isSatisfiedBy` needs to raise an exception instead of just
        // returning false.
        // If it does just return false: that's a logic exception, fix it
        if (!$spec->isSatisfiedBy($grid->getRowSet())) {
            throw new \InvalidArgumentException("Save of row set failed for unknown reason");
        }


        // usages are a join table with a sequence, not a strict "object" - as such, we can purge the relevant records and simply re-create them
        $indicatorUsageMapper  = IndicatorUsageMapper::getInstance();
        $capabilityUsageMapper = CapabilityUsageMapper::getInstance();
        $indicatorUsageModels  = $indicatorUsageMapper->createCollection();
        $capabilityUsageModels = $capabilityUsageMapper->createCollection();
        $descriptorModels      = $descriptorMapper->createCollection();
        $capabilitySequence    = CapabilityUsageModel::SEQUENCE_BASE;
        $indicatorUsageMapper->deleteByColumn('submission_box_id', $submissionBox->getId());
        $capabilityUsageMapper->deleteByColumn('submission_box_id', $submissionBox->getId());

        foreach ($grid->getCapabilityUsages() as $capabilityUsage) {
            $capabilityMaxValue = $capabilityUsage->getMaxValue();

            $capabilityUsageModel = $capabilityUsageMapper->createModel()
                ->setSubmissionBox($submissionBox)
                ->setCapability($capabilityModels[$capabilityUsage->getCapability()->getId()])
                ->setMaxValue($capabilityMaxValue)
                ->setSequence($capabilitySequence++);
            $capabilityUsageModels->add($capabilityUsageModel);

            $indicatorSequence = IndicatorUsageModel::SEQUENCE_BASE;
            foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
                $indicatorModel      = $indicatorModels[$indicatorUsage->getIndicator()->getId()];
                $indicatorUsageModel = $indicatorUsageMapper->createModel()
                    ->setSubmissionBox($submissionBox)
                    ->setIndicator($indicatorModel)
                    ->setMaxValue($indicatorUsage->calculateMaxValueFromDescriptors())
                    ->setSequence($indicatorSequence++);
                $indicatorUsageModels->add($indicatorUsageModel);

                // Descriptors
                foreach ($grid->getLevels() as $levelIndex => $level) {
                    $descriptor = $indicatorUsage->retrieveDescriptor($levelIndex);
                    if ($descriptor) {
                        $descriptorModel = $descriptorMapper->createModel()
                            ->setId($descriptor->getId())
                            ->setIndicator($indicatorModel)
                            ->setLevel($levelMapper->createModel($level->getId()))
                            // One day: set min value here
                            ->setMaxValue($descriptor->getMaxValue())
                            ->setDescription($descriptor->getDescription());
                        $descriptorModels->add($descriptorModel);
                    }
                }
            }
        }

        $capabilityMapper->save($capabilityModels);
        $indicatorMapper->save($indicatorModels);
        $capabilityUsageMapper->save($capabilityUsageModels);
        $indicatorUsageMapper->save($indicatorUsageModels);
        $descriptorMapper->save($descriptorModels);


        // wash up before dinner, kids


        // Invalidate all marks, if the grid and original grid are incompatible
        if ($originalGrid && !$grid->isCompatibleWith($originalGrid)) {
            $this->invalidateMarks($grid->getAssessment()->getId());
        }


        // remove all levels that have zero descriptors attached - extract from the descriptors
        $seenLevels = [];
        foreach ($descriptorModels as $desc) {
            $seenLevels[$desc->getLevel()->getId()] = true;
        }
        $seenLevels = array_keys($seenLevels);
        $this->tidyUp($levelMapper, ['box_id' => $submissionBox->getId()], $seenLevels);


        // remove all descriptors that are on this box, but are not among the descriptors we just saved (it indicates blankness / unused-ness)
        $levelIds = $levelMapper->findIdsByColumn('box_id', $submissionBox->getId(), ['deleted' => true]);
        $this->tidyUp($descriptorMapper, ['level_id' => $levelIds], $descriptorModels->getPropertyValues('id'));
    }


    /**
     * Retrieve a marked grid for the given feedback.
     *
     * @param IFeedback $feedback
     * @return MarkedGrid
     * @throws MapperException
     */
    public function retrieveMarkedGrid(IFeedback $feedback): MarkedGrid
    {
        $grid       = $this->retrieveGrid($feedback->getAssessable(), false);
        $rowSet     = $grid->getRowSet();

        $markSchemeFactory = new MarkSchemeFactory();
        $gridMarkScheme    = $feedback->getAssessable()->getMarkScheme();

        $markedGrid = new MarkedGrid($feedback, $grid, $gridMarkScheme);

        // What goes in the grid?
        // * Capability marks
        // * Descriptor marks
        // ** more correctly known as indicator marks, which reference a descriptor
        /** @var GenericCollection|CapabilityMarkModel[] $capabilityMarkModels */
        $capabilityMarkModels = CapabilityMarkMapper::getInstance()->findByColumnList([
            'return_id' => $feedback->getId(),
            'valid'     => true,
        ]);

        /** @var Collection|GenericCollection[]|IndicatorMarkModel[] $indicatorMarkModelsByCapability */
        $indicatorMarkModelsByCapability = IndicatorMarkMapper::getInstance()->findByColumnList([
            'rubric_capability_mark_id' => $capabilityMarkModels->getPropertyValues('id'),
            'valid'                     => true,
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
        $capabilityMapper     = CapabilityMapper::getInstance();
        $descriptorMapper     = DescriptorMapper::getInstance();
        $indicatorMarkMapper  = IndicatorMarkMapper::getInstance();
        $capabilityMarkMapper = CapabilityMarkMapper::getInstance();
        $returnedWorkMapper   = SubmissionBox\ReturnedWorkMapper::getInstance();


        // Blow away any previous cap/ind marks for this feedback
        $returnId = $markedGrid->getFeedback()->getId();
        $indicatorMarkMapper->deleteFeedbackMarks($returnId);
        $capabilityMarkMapper->deleteFeedbackMarks($returnId);


        // What am I saving marks for?
        $returnedWork = $returnedWorkMapper->findById($returnId);


        // Create list of cap/ind marks that will be saved
        $capabilityMarksToSave = $capabilityMarkMapper->createCollection();
        $indicatorMarksToSave  = $indicatorMarkMapper->createCollection();
        foreach ($markedGrid->getCapabilityMarks() as $capabilityMark) {
            // Create cap mark
            $capabilityId = $capabilityMark->getCapabilityUsage()->getCapability()->getId();
            $capabilityModel = $capabilityMapper->findById($capabilityId);
            $capabilityMarksToSave[$capabilityId] = $capabilityMarkModel = $capabilityMarkMapper->createModel()
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
                    $descriptorModel = $descriptorMapper->findById($indicatorMark->getDescriptor()->getId());
                }
                if ($descriptorModel === null) {
                    // Do not create, if descriptor is not set
                    continue;
                }
                $indicatorMarksToSave[$indicatorId] = $indicatorMarkMapper->createModel()
                    ->setCapabilityMark($capabilityMarkModel)
                    ->setDescriptor($descriptorModel)
                    ->setValue($indicatorMark->getValue())
                    ->setValid(true);
            }
        }


        // And save cap/ind marks
        $capabilityMarkMapper->save($capabilityMarksToSave);
        $indicatorMarkMapper->save($indicatorMarksToSave);
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
    public function hasMarkedGrid(Participant $participant, array $feedbackTypes): bool
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
        $validCapMarkCount = IndicatorMarkMapper::getInstance()->countByColumnList([
            'r.id'  => $feedbackIds,
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
     * @return void
     */
    public function importGridFromCsv(IStorage $file, DueWorkModel $dueWork, int $maxLevels): void
    {
        // Verify that the file is actually a CSV.
        // (I think extension checking is the most reliable way to do this. We
        //  have had issues with mimetype checking in the past, because the CSV
        //  mimetype isn't always recognized by the browser's operating system.)
        if (!(strtolower($file->getExtension()) === 'csv')) {
            throw new UnexpectedValueException('Only CSV files may be uploaded');
        }


        // Create the factory
        $markTypeMapper  = SubmissionBox\MarkTypeMapper::getInstance();
        $importerFactory = new CsvImporterFactory($markTypeMapper);
        // TODO inject (like every other mapper here)
        $filesystemProxy = StorageMapper::getInstance()->getStorageFilesystemProxy();
        $importer        = $importerFactory->build($filesystemProxy->readStream($file), $maxLevels, $dueWork);
        $this->importGrid($dueWork, $importer);
    }

    /**
     * Imports a rubric grid using importer into the given due work.
     * @param DueWorkModel $dueWork
     * @param IImporter    $importer
     * @throws MapperException
     */
    public function importGrid(DueWorkModel $dueWork, IImporter $importer): void
    {
        $markTypeMapper  = SubmissionBox\MarkTypeMapper::getInstance();

        // Add to the rowset as necessary
        $rowSet        = $this->retrieveRowSet($dueWork->getContext(), false);
        $rowsetChanged = false;
        foreach ($importer->retrieveCapabilitiesWithIndicators() as $capability) {
            // Is this capability within the rowset? If not add it
            $capName        = $capability['name'];
            $thisCapability = $rowSet->retrieveCapabilityByName($capName);
            if (!$thisCapability) {
                $rowsetChanged  = true;
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
            $rowSet = $this->retrieveRowSet($dueWork->getContext(), false, false);
        }


        // Now start building the grid
        $capabilityMarkType   = $markTypeMapper->createModel()->setId($importer->getCapabilityMarkType());
        $capabilityGradeGroup = Group\Mapper::getInstance()->createModel()->setId($importer->getCapabilityGradeGroup());

        $dueWork
            ->setCapabilityMarkType($capabilityMarkType)
            ->setCapabilityGradeGroup($capabilityGradeGroup)
            ->setUseMarkRange($importer->isUseMarkRange());
        $grid = new Grid($dueWork, $rowSet);


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
                        $capInUse         = true;
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

        $dueWork->setRubricGrid($grid);
    }


    /**
     * Copies the selected descriptors from one submission return to another.
     *
     * @param int $sourceReturnId
     * @param int $destinationReturnId
     * @throws \Schoolbox\Mapper\Exception
     */
    public function copySelections($sourceReturnId, $destinationReturnId)
    {
        // Don't do anything if the source and dest are the same
        if ($sourceReturnId === $destinationReturnId) {
            return;
        }
        $capabilityMarkMapper       = CapabilityMarkMapper::getInstance();
        $descriptorMarkMapper       = IndicatorMarkMapper::getInstance();
        $sourceDescriptorMarks      = $descriptorMarkMapper->findByColumn('g.return_id', $sourceReturnId);
        $destinationCapabilityMarks = $capabilityMarkMapper->createCollection();
        $destinationDescriptorMarks = $descriptorMarkMapper->createCollection();
        $destinationReturn          = SubmissionBox\ReturnedWorkMapper::getInstance()->createModel($destinationReturnId);

        foreach ($sourceDescriptorMarks as $sourceDescriptor) {
            // Is this capability marks set?
            $sourceCapabilityMark = $sourceDescriptor->getCapabilityMark();
            $capability           = $sourceCapabilityMark->getCapability();
            $capabilityId         = $capability->getId();
            if (!$destinationCapabilityMarks[$capabilityId]) {
                $destinationCapabilityMarks[$capabilityId] = $capabilityMarkMapper->createModel()
                    ->setCapability($capability)
                    ->setSubmissionReturn($destinationReturn)
                    ->setValue($sourceCapabilityMark->getValue())
                    ->setHumanReadableValue($sourceCapabilityMark->getHumanReadableValue())
                    ->setValid($sourceCapabilityMark->isValid());
            }

            $destinationDescriptorMarks->add(
                $descriptorMarkMapper->createModel()
                    ->setCapabilityMark($destinationCapabilityMarks[$capabilityId])
                    ->setDescriptor($sourceDescriptor->getDescriptor())
                //                    ->setSubmissionReturn($destinationReturn)
                    ->setValue($sourceDescriptor->getValue())
                    ->setValid($sourceDescriptor->isValid())
            );
        }
        $capabilityMarkMapper->save($destinationCapabilityMarks);
        $descriptorMarkMapper->save($destinationDescriptorMarks);
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
        $query = "
            UPDATE     rubric_indicator_mark rdm
            INNER JOIN rubric_capability_mark  rgm ON rdm.rubric_capability_mark_id = rgm.id
            INNER JOIN submission_return       sr  ON rgm.return_id                 = sr.id
            INNER JOIN submission_box          sb  ON sr.box_id                     = sb.id
            SET        rdm.valid = FALSE,
                       rgm.valid = FALSE
            WHERE      sb.assessment_id = :assessmentId
        ";

        $this->database->prepare($query)->execute(['assessmentId' => $assessmentId]);
    }


    /**
     * @param IMapper $mapper
     * @param array   $search
     * @param array   $exclude
     *
     * @return bool
     */
    protected function tidyUp(IMapper $mapper, array $search, array $exclude)
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
     * @param GenericCollection|DueWorkModel[] $dueWorks
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

        $where               = implode(' AND ', $pb->getWhere());
        $capabilityStatement = $this->database->prepare(
            "
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
                ORDER BY a.due_date, vr.capability_sequence, vr.indicator_sequence, a.id;
            "
        );
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

            $where            = implode(' AND ', $pb->getWhere());
            $averageStatement = $this->database->prepare(
                "
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
                FROM       rubric_indicator_mark  rim
                INNER JOIN rubric_capability_mark rcm ON rim.rubric_capability_mark_id = rcm.id
                INNER JOIN rubric_capability       rc ON rcm.rubric_capability_id = rc.id
                INNER JOIN rubric_descriptor       rd ON rim.descriptor_id = rd.id
                INNER JOIN rubric_level            rl ON rd.level_id = rl.id
                INNER JOIN rubric_indicator        ri ON rd.rubric_indicator_id = ri.id
                INNER JOIN submission_return       sr ON rcm.return_id = sr.id
                INNER JOIN submission_box          sb ON sr.box_id = sb.id
                INNER JOIN assessment               a ON sb.assessment_id = a.id
                WHERE {$where}
                ORDER BY a.due_date ASC;
            "
            );
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

            $where            = implode(' AND ', $pb->getWhere());
            $averageStatement = $this->database->prepare(
                "
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
                ORDER BY a.due_date ASC;
            "
            );
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
                    $boxPlot                                                                                         = new Summary($indicator);
                    $longitude[$capabilityId]['indicators'][$indicatorId]['activities'][$assessmentId]['statistics'] = $boxPlot->getSummary();
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
     *
     * @return bool Whether a new Rubric was created.
     * @throws MapperException
     * @throws MapperException\Deleted
     */
    public function copyRubric($sourceAssessmentId, $destinationAssessmentId)
    {
        // TODO extract this entire monstrosity elsewhere

        // Get required mappers
        $boxMapper             = SubmissionBoxMapper::getInstance();
        $levelMapper           = LevelMapper::getInstance();
        $descriptorMapper      = DescriptorMapper::getInstance();
        $capabilityMapper      = CapabilityMapper::getInstance();
        $indicatorMapper       = IndicatorMapper::getInstance();
        $capabilityUsageMapper = CapabilityUsageMapper::getInstance();
        $indicatorUsageMapper  = IndicatorUsageMapper::getInstance();

        $sourceBox = $boxMapper->findOneByColumn('assessment_id', $sourceAssessmentId);

        /** @var LevelModel[]|GenericCollection $srcLevels */
        $srcLevels = $levelMapper->findByColumn('box_id', $sourceBox->getId())->indexBy('id');

        if (! $sourceBox->getUseRubric()) {
            // No rubric? Can't work with that - die.
            return false;
        }

        $dstBox    = $boxMapper->findOneByColumn('assessment_id', $destinationAssessmentId);
        $dstFolder = $dstBox->getCategory();

        // Cannot mutate identity of objects returned from mappers.
        // Index all these collections by source version ID, even though the object will be destination version.
        /** @var LevelModel[]|GenericCollection $dstLevels */
        $dstLevels = $levelMapper->createCollection();
        /** @var DescriptorModel[]|GenericCollection $dstDescriptors */
        $dstDescriptors = $descriptorMapper->createCollection();
        /** @var CapabilityModel[]|GenericCollection $dstCapabilities */
        $dstCapabilities = $capabilityMapper->createCollection();
        /** @var IndicatorModel[]|GenericCollection $dstIndicators */
        $dstIndicators = $indicatorMapper->createCollection();
        /** @var CapabilityUsageModel[]|GenericCollection $dstCapabilityUsages */
        $dstCapabilityUsages = $capabilityUsageMapper->createCollection();
        /** @var IndicatorUsageModel[]|GenericCollection $dstIndicatorUsages */
        $dstIndicatorUsages = $indicatorUsageMapper->createCollection();

        // Copy Levels
        foreach ($srcLevels as $srcLevel) {
            $dstLevel = clone $srcLevel;
            $dstLevel->setId(null);
            $dstLevel->setSubmissionBox($dstBox);
            $dstLevel->setDescriptors($descriptorMapper->createCollection());
            $dstLevels->setOffset($srcLevel->getId(), $dstLevel);
        }

        // Get Source Descriptors
        /** @var DescriptorModel[]|GenericCollection $srcDescriptors */
        $srcDescriptors  = $descriptorMapper->findByColumn('level_id', $srcLevels->getPropertyValues('id'))->indexBy('id');
        if ($srcDescriptors->count() === 0) {
            // no descriptors are set for source assessment
            return false;
        }

        $srcIndicators   = $indicatorMapper->createCollection();
        $srcCapabilities = $capabilityMapper->createCollection();

        /* Get existing destination indicators.
         *
         * If during destination object graph build, we detect that a source descriptor
         * already exists on destination, use the destination version instead of creating a new indicator
         *
         * For now, need to by-pass cache, as we may have inserted indicators during previous call to this function.
         * //TODO - the mappers should be invalidating the SELECT cache on INSERT/UPDATE @see SA-1951
         */
        /** @var DescriptorModel $firstSrcDescriptor */
        $firstSrcDescriptor = $srcDescriptors->first();
        $parentType         = $firstSrcDescriptor->getIndicator()->getCapability()->getParentType();
        $existingIndicators = $indicatorMapper->findForParent(
            $dstFolder->getId(),
            $parentType,
            false
        );

        // If the rubric is being duped into a unit spec:
        // also fetch the list of course-level capabilities and indicators
        $unitSpec = $dstFolder->getUnitSpecification();
        if ($unitSpec) {
            $existingIndicators = $existingIndicators->merge(
                $indicatorMapper->findForParent(
                    $unitSpec->getFolder()->getParent()->getId(),
                    $parentType,
                    false
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
                $srcCapability = $capabilityMapper->findById($srcDescriptor->getIndicator()->getCapability()->getId());
                $dstCapability = clone $srcCapability;
                $dstCapability->setId(null);
                $dstCapability->setParentId($existingCapabilityMap[$capabilityName]->getParentId());
                $existingCapabilityMap[$capabilityName] = $dstCapability;
            }
            $dstCapabilities->setOffset($srcCapabilityId, $dstCapability);


            // Did we find an existing indicator with the same name?
            if ($duplicateIndicator) {
                $dstIndicator = $existingIndicatorMap[$capabilityName][$indicatorName];
            } else {
                // hydrate to get curriculum nodes
                $srcIndicator = $indicatorMapper->findById($srcDescriptor->getIndicator()->getId());
                $dstIndicator = clone $srcIndicator;
                $dstIndicator->setId(null);
                $dstIndicator->setCapability($dstCapability);
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
        /** @var CapabilityUsageModel[]|GenericCollection $srcCapabilityUsages */
        $srcCapabilityUsages = $capabilityUsageMapper->findByColumnList([
            'rubric_capability_id' => $srcCapabilities->getPropertyValues('id'),
            'submission_box_id'    => $sourceBox->getId(),
        ]);

        /** @var IndicatorUsageModel[]|GenericCollection $srcIndicatorUsages */
        $srcIndicatorUsages = $indicatorUsageMapper->findByColumnList([
            'rubric_indicator_id' => $srcIndicators->getPropertyValues('id'),
            'submission_box_id'   => $sourceBox->getId(),
        ]);

        // Copy capability Usages
        foreach ($srcCapabilityUsages as $srcCapabilityUsage) {
            $dstCapabilityUsage = clone $srcCapabilityUsage;

            /** @var CapabilityModel $dstCapability */
            $dstCapability = $dstCapabilities->getOffset($srcCapabilityUsage->getCapability()->getId());

            $dstCapabilityUsage->setCapability($dstCapability);
            $dstCapabilityUsage->setSubmissionBox($dstBox);
            $dstCapabilityUsages->add($dstCapabilityUsage);
        }

        // Copy indicator usages
        foreach ($srcIndicatorUsages as $srcIndicatorUsage) {
            $dstIndicatorUsage = clone $srcIndicatorUsage;

            /** @var IndicatorModel $dstIndicator */
            $dstIndicator = $dstIndicators->getOffset($srcIndicatorUsage->getIndicator()->getId());

            $dstIndicatorUsage->setIndicator($dstIndicator);
            $dstIndicatorUsage->setSubmissionBox($dstBox);
            $dstIndicatorUsages->add($dstIndicatorUsage);
        }

        // Save (order is important to ensure IDs are assigned to parents b4 saving children)
        $levelMapper->save($dstLevels);
        $capabilityMapper->save($dstCapabilities);
        $indicatorMapper->save($dstIndicators);
        $descriptorMapper->save($dstDescriptors);
        $capabilityUsageMapper->save($dstCapabilityUsages);
        $indicatorUsageMapper->save($dstIndicatorUsages);

        return true;
    }

    /**
     * Deletes all traces of a rubric from the due work identified by
     * `$assessmentId`. This includes:
     * * capability and indicator usages
     * * levels
     * * descriptors on levels
     *
     * @param int $assessmentId
     * @return void
     */
    public function deleteRubric(int $assessmentId): void
    {
        $boxMapper = SubmissionBoxMapper::getInstance();
        $box       = $boxMapper->findOneByColumn('assessment_id', $assessmentId);

        $capabilityUsageMapper = CapabilityUsageMapper::getInstance();
        $capabilityUsageMapper->deleteByColumn('submission_box_id', $box->getId());

        $indicatorUsageMapper = IndicatorUsageMapper::getInstance();
        $indicatorUsageMapper->deleteByColumn('submission_box_id', $box->getId());

        $levelMapper = LevelMapper::getInstance();
        $levels      = $levelMapper->findByColumn('box_id', $box->getId());
        $levelMapper->delete($levels);

        $descriptorMapper = DescriptorMapper::getInstance();
        $descriptorMapper->deleteByColumn('level_id', $levels->getPropertyValues('id'));
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
        $pb->in('rg.parent_id', $folderIds)
           ->equals('rg.parent_type', CapabilityModel::PARENT_TYPE_CATEGORY)
           ->greaterThanOrEqualTo('a.due_date', $term->getStart()->getDatabase())
           ->equals('sr.review_route', SubmissionBox\ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
           ->isNull('a.deleted_at')
           ->notNull('sr.published_at')
           ->isNull('sr.deleted_at')
           ->isNull('u.deleted_at');

        $finish = $term->getActualFinish();
        if (null !== $finish && !$finish->isNull()) {
            $pb->lessThan('a.due_date', $finish->getDatabase());
        }

        $where               = implode(' AND ', $pb->getWhere());
        $assessmentStatement = $this->database->prepare("
            SELECT
                sr.owner AS studentId,
                sr.id    AS feedbackId,
                a.id     AS assessmentId
            FROM submission_return sr
            INNER JOIN user                      u ON sr.owner = u.id
            INNER JOIN submission_box           sb ON sr.box_id = sb.id
            INNER JOIN rubric_capability_usage rgu ON sb.id = rgu.submission_box_id
            INNER JOIN rubric_capability        rg ON rgu.rubric_capability_id = rg.id
            INNER JOIN assessment                a ON sb.assessment_id = a.id
            WHERE {$where}
            ORDER BY sr.published_at ASC
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

        $where             = implode(' AND ', $pb->getWhere());
        $maxMarksStatement = $this->database->prepare("
            SELECT
                a.id,
                ri.id         AS criterionId,
                rc.name       AS grouping,
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

        $where               = implode(' AND ', $pb->getWhere());
        $descriptorStatement = $this->database->prepare("
            SELECT
                sr.owner AS studentId,
                rc.id    AS criterionId,
                rg.name  AS grouping,
                rc.name,
                a.id,
                a.title,
                rd.description,
                rdm.value
            FROM       rubric_indicator_mark  rdm
            INNER JOIN rubric_capability_mark rgm ON rdm.rubric_capability_mark_id = rgm.id
            INNER JOIN rubric_capability       rg ON rgm.rubric_capability_id = rg.id
            INNER JOIN rubric_descriptor       rd ON rdm.descriptor_id = rd.id
            INNER JOIN rubric_indicator        rc ON rd.rubric_indicator_id = rc.id
            INNER JOIN submission_return       sr ON rgm.return_id = sr.id
            INNER JOIN submission_box          sb ON sr.box_id = sb.id
            INNER JOIN assessment               a ON sb.assessment_id = a.id
            WHERE {$where}
            ORDER BY rg.name ASC, rc.name ASC, a.due_date DESC;
        ");
        $descriptorStatement->execute($pb->getParams());


        // Step 4: smoosh them all together
        foreach ($descriptorStatement as $row) {
            $boxId       = $row['id'];
            $indicatorId = $row['criterionId'];
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
    public function getAvailableIndicators(array $parentIds, $parentType = CapabilityModel::PARENT_TYPE_CATEGORY)
    {
        if (!count($parentIds)) {
            return [];
        }

        $pb = new Builder($this->database->getQuoter(), '');
        $pb->in('rg.parent_id', $parentIds)
           ->equals('rg.parent_type', $parentType);

        $whereClause = implode(' AND ', $pb->getWhere());

        $stmt = $this->database->prepare("
            SELECT rg.name AS grouping, rc.id, rc.name
            FROM rubric_indicator  rc
            JOIN rubric_capability rg ON rg.id = rc.rubric_capability_id AND rg.deleted_at IS NULL
            WHERE {$whereClause}
            AND rc.deleted_at IS NULL
            AND rg.deleted_at IS NULL
            ORDER BY rg.name ASC, rc.name ASC
        ");
        $stmt->execute($pb->getParams());

        $indicators = [];
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

        $where             = implode(' AND ', $pb->getWhere());
        $feedbackStatement = $this->database->prepare(
            "
            SELECT
                sr.id    AS feedbackId,
                a.id     AS assessmentId,
                sr.owner AS studentUserId
            FROM submission_return sr
            INNER JOIN submission_box sb ON sr.box_id = sb.id
            INNER JOIN assessment      a ON sb.assessment_id = a.id
            WHERE {$where}
            ORDER BY sr.published_at ASC
        "
        );
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
     * (From Rubric Markbook Controller)
     * @param array $a
     * @param array $b
     * @return int
     */
    private function sortByName($a, $b)
    {
        return strnatcasecmp($a['name'], $b['name']);
    }
}
