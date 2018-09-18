<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Alaress\Database\Predicate\Builder;
use Database;
use PDO;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Rubric\Competency\Level as CompetencyLevel;
use Schoolbox\Assessment\Rubric\Criteria;
use Schoolbox\Assessment\Rubric\Factory;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark;
use Schoolbox\Assessment\Rubric\MarkedGrid\IndicatorMark;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\Assessment\Rubric\RowSet\RowSet;
use Schoolbox\Assessment\Rubric\ValidCapabilityMax;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\IMapper;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Assessment\Term\Model as TermModel;
use Schoolbox\User;
use UnderflowException;

/**
 * Umm. Not *exactly* a mapper... at the moment it's more like a handler...
 *
 * Class Mapper
 *
 * @package Schoolbox\Assessment\Rubric
 */
class RubricMapper
{

    /**
     * Stores the singleton instance of the mapper
     *
     * @var array[Mapper]
     */
    static protected $instance;

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

    /** @var Factory */
    protected $factory;

    /** */
    public function __construct()
    {
        $this->database = Database::getInstance();
        $this->factory  = new Factory(new Rubric());
    }


    /**
     * Retrieves a rowset for the given context.
     *
     * @todo consider whether this belongs here, or in some other class
     *
     * @param AssessmentContext $context
     * @param bool              $getUsages
     * @return RowSet
     * @throws MapperException
     */
    public function retrieveRowSet(AssessmentContext $context, bool $getUsages): RowSet
    {
        $rowSet = new RowSet($context);

        // Load the capabilities and indicators for this context
        $folder = $context->getBaseContext()->getFolder();

        // Capability contains indicators too: inside-out them
        /** @var GenericCollection|\Schoolbox\Assessment\Rubric\RowSet\Capability[] $capabilities */
        $capabilities    = new GenericCollection(\Schoolbox\Assessment\Rubric\RowSet\Capability::class);
        $indicatorModels = CriterionMapper::getInstance()->findForFolder($folder->getId());

        // Unit-specs have their course spec's capabilities/indicators as well
        $courseSpecFolderId = null;
        if ($context instanceof UnitSpecification || $folder->hasUnitSpecification()) {
            $courseSpecFolderId = (int)$folder->getParent()->getId();
            $indicatorModels    = CriterionMapper::getInstance()->findForFolder($courseSpecFolderId)
                                                                ->merge($indicatorModels);
        }

        // This is how it SHOULD work...
//        if ($context instanceof UnitSpecification) {
//            $indicatorModels = $indicatorModels->merge(
//                CriterionMapper::getInstance()->findForFolder(
//                    $context->getCourse()->getFolder()->getId()
//                )
//            );
//        }

        foreach ($indicatorModels as $indicatorModel) {
            $capabilityModel    = $indicatorModel->getGroup();
            $isCourseCapability = $capabilityModel->getParentId() === $courseSpecFolderId;
            $capabilityId       = $capabilityModel->getId();
            $indicatorId        = $indicatorModel->getId();
            if (!isset($capabilities[$capabilityId])) {
                $capabilities[$capabilityId] = (new \Schoolbox\Assessment\Rubric\RowSet\Capability())
                    ->setId($capabilityId)
                    ->setName($capabilityModel->getName())
                    ->setIsCourseLevel($isCourseCapability)
                    ->setCurriculumNodes($capabilityModel->getCurriculumNodes());

                if ($getUsages) {
                    foreach (CriterionUsageMapper::getInstance()->findGroupUsage($capabilityId) as $capabilityUsage) {
                        $capabilities[$capabilityId]->getUsage()[$capabilityUsage['id']] = (new DueWork\Model())
                            ->setId($capabilityUsage['id'])
                            ->setTitle($capabilityUsage['title']);
                    }
                }
            }
            $capabilities[$capabilityId]->getIndicators()[$indicatorId] = (new \Schoolbox\Assessment\Rubric\RowSet\Indicator())
                ->setId($indicatorId)
                ->setName($indicatorModel->getName())
                ->setCurriculumNodes($indicatorModel->getCurriculumNodes());

            if ($getUsages) {
                foreach (CriterionUsageMapper::getInstance()->findCriterionUsage($indicatorId) as $indicatorUsage) {
                    $capabilities[$capabilityId]->getIndicators()[$indicatorId]->getUsage()[$indicatorUsage['id']]
                        = (new DueWork\Model())
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
     * @param RowSet           $rowSet
     * @param bool             $persistCurriculum    Should curriculum nodes for groups and
     *                                               criteria be updated?
     * @param bool             $allowModifyUsed      Should groups/criteria that are in use
     *                                               be modifiable or deletable?
     * @param null|IAssessable $allowModifyExclusion If set and `$allowModifyUsed` is off, a
     *                                               group or criterion in `$rowSet` MAY be
     *                                               modified if it is used by no other
     *                                               assessment than this one.
     * @throws MapperException
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
        $capabilityMapper         = GroupMapper::getInstance();
        $indicatorMapper          = CriterionMapper::getInstance();
        $errorMapper              = ErrorMapper::getInstance();

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

        // Track names used
        $capabilityNamesUsed      = [];


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
                $capabilityModel = $capabilityMapper->createModel()
                    ->setId($capabilityId)
                    ->setParentId($folder->getId())
                    ->setParentType(Group::PARENT_TYPE_CATEGORY);
            }

            // Validate whether name can be changed
            $allowNameChange = true;
            if ($existingCapability && !$allowModifyUsed && $existingCapability->isInUse($allowModifyExclusion)) {
                $allowNameChange = false;
            }
            if ($allowNameChange) {
                $capabilityModel->setName($capability->getName());
            }

            // Validate if this name has already been used by a capability whose
            // name can be changed
            $normalizedName = $this->normaliseForComparison($capability->getName());
            if (isset($capabilityNamesUsed[$normalizedName])) {
                $capabilityNamesUsed[$normalizedName] = $capabilityNamesUsed[$normalizedName] || $allowNameChange;
                if ($capabilityNamesUsed[$normalizedName]) {
                    // this is used by a capability which is allowed to change:
                    // invalidate
                    throw $errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
                }
            } else {
                $capabilityNamesUsed[$normalizedName] = $allowNameChange;
            }

            // Validate whether curriculum code can be changed
            if ($persistCurriculum) {
                $capabilityModel->setCurriculumNodes($capability->getCurriculumNodes());
            }

            // Add capability to list
            $newCapabilityModels->add($capabilityModel);

            // Set each indicator in the capability aside to be persisted
            $indicatorNamesUsed = [];
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
                        ->setGroup($capabilityModel);
                }

                // Validate whether name can be changed
                $allowNameChange = true;
                if ($existingIndicator && !$allowModifyUsed && $existingIndicator->isInUse($allowModifyExclusion)) {
                    $allowNameChange = false;
                }
                if ($allowNameChange) {
                    $indicatorModel->setName($indicator->getName());
                }

                // Validate if this name has already been used by a indicator
                // whose name can be changed
                $normalizedName = $this->normaliseForComparison($indicator->getName());
                if (isset($indicatorNamesUsed[$normalizedName])) {
                    $indicatorNamesUsed[$normalizedName] = $indicatorNamesUsed[$normalizedName] || $allowNameChange;
                    if ($indicatorNamesUsed[$normalizedName]) {
                        // this is used by a indicator which is allowed to
                        // change: invalidate
                        throw $errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
                    }
                } else {
                    $indicatorNamesUsed[$normalizedName] = $allowNameChange;
                }

                // Validate whether curriculum code can be changed
                if ($persistCurriculum) {
                    $indicatorModel->setCurriculumNodes($indicator->getCurriculumNodes());
                }

                // Add indicator to list
                $newIndicatorModels->add($indicatorModel);
            }
        }


        // Validate whether capabilities/indicators marked for deletion may
        // actually be removed
        $deletedCapabilityIds = array_diff($existingCapabilityIds, $newCapabilityIds);
        $deletedIndicatorIds  = array_diff($existingIndicatorIds, $newIndicatorIds);
        if (!$allowModifyUsed) {
            // Validate deleted capabilities aren't in use
            foreach ($deletedCapabilityIds as $deletedCapabilityId) {
                $existingCapability = $existingRowSet->retrieveCapability($deletedCapabilityId);
                if ($existingCapability && $existingCapability->isInUse($allowModifyExclusion)) {
                    throw $errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
                }
            }

            // Validate deleted indicators aren't in use
            foreach ($deletedIndicatorIds as $deletedIndicatorId) {
                $existingIndicator = $existingRowSet->retrieveIndicator($deletedIndicatorId);
                if ($existingIndicator && $existingIndicator->isInUse($allowModifyExclusion)) {
                    throw $errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
                }
            }
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
     * @param IAssessable $assessment
     * @param bool        $getUsages
     * @return Grid
     * @throws MapperException
     */
    public function retrieveGrid(IAssessable $assessment, bool $getUsages): Grid
    {
        // Build the rowset first
        $grid = new Grid($assessment, $this->retrieveRowSet($assessment->getContext(), $getUsages));

        // Not a due work? This has an empty grid
        if (!$assessment instanceof DueWork\Model) {
            return $grid;
        }

        // Assessment isn't persisted yet? This has an empty grid
        if (!$assessment->getId()) {
            return $grid;
        }

        // Load the levels, selected capabilities+indicators, descriptors for
        // the assessment
        $capabilityUsageModels = GroupUsageMapper::getInstance()->findByColumn(
            'sb.assessment_id',
            $assessment->getId(),
            ['order' => ['sequence' => 'asc',]]
        )->indexByCallback(function (GroupUsage $gu) {
            return $gu->getGroup()->getId();
        });
        $indicatorUsageModels = CriterionUsageMapper::getInstance()->findByColumn(
            'sb.assessment_id',
            $assessment->getId(),
            ['order' => ['sequence' => 'asc',]]
        )->indexByCallback(function (CriterionUsage $cu) {
            return $cu->getCriterion()->getId();
        });
        $levelModels = LevelMapper::getInstance()->findByColumn(
            'b.assessment_id',
            $assessment->getId(),
            ['order' => ['sequence' => 'asc',]]
        )->indexByCallback(function (Level $l) {
            return $l->getId();
        });
        $descriptorModels = DescriptorMapper::getInstance()->findByColumnList([
            'criterion_id' => $indicatorUsageModels->keys(),
            'level_id'     => $levelModels->keys(),
        ]);

        $groupedIndicatorUsageModels = $indicatorUsageModels->groupByCallback(
            function (CriterionUsage $cu) {
                return $cu->getCriterion()->getGroup()->getId();
            }
        );


        // Create descriptor models
        $descriptorsByLevel     = [];
        $descriptorsByIndicator = [];
        foreach ($descriptorModels as $descriptorModel) {
            $levelId     = $descriptorModel->getLevel()->getId();
            $indicatorId = $descriptorModel->getCriterion()->getId();
            $descriptor  = (new \Schoolbox\Assessment\Rubric\Grid\Descriptor())
                ->setId($descriptorModel->getId())
                ->setDescription($descriptorModel->getDescription())
                ->setMaxValue($descriptorModel->getMaxValue());

            $descriptorsByIndicator[$indicatorId][$levelId] = $descriptor;
            $descriptorsByLevel[$levelId][$indicatorId]     = $descriptor;
        }


        // Add levels
        /** @var Level[] $levelModels */
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
            /** @var GroupUsage $capabilityUsageModel */
            $capabilityModel = $capabilityUsageModel->getGroup();
            $capabilityId    = $capabilityModel->getId();
            $capability      = $grid->getRowSet()->retrieveCapability($capabilityId);
            if (!$capability) {
                continue;
            }

            $capabilityUsage = (new \Schoolbox\Assessment\Rubric\Grid\CapabilityUsage($capability))
                ->setMaxValue($capabilityUsageModel->getMaxValue());

            $capabilityIndicatorUsageModels = $groupedIndicatorUsageModels[$capabilityId] ?? [];
            foreach ($capabilityIndicatorUsageModels as $indicatorUsageModel) {
                /** @var CriterionUsage $indicatorUsageModel */
                $indicatorModel = $indicatorUsageModel->getCriterion();
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

            $grid->getCapabilityUsages()[$capabilityId] = $capabilityUsage;
        }

        return $grid;
    }


    /**
     * Saves this grid to the database.
     *
     * @param Grid $grid
     * @throws MapperException if something happens in the mapper
     */
    public function persistGrid(Grid $grid): void
    {
        // The mappers still deal in submission boxes, but we deal in levels
        // If this exceptioned out, it was never meant to be
        $submissionBox = SubmissionBoxMapper::getInstance()->findOneByColumn(
            'assessment_id',
            $grid->getAssessment()->getId()
        );

        // TODO some way to wrap all this in a transaction would be nice... -_-;
        // (The answer exists: merge SA-6405)
        $levelMapper         = LevelMapper::getInstance();
        $descriptorMapper    = DescriptorMapper::getInstance();
        $indicatorMapper     = CriterionMapper::getInstance();
        $capabilityMapper    = GroupMapper::getInstance();
        $existingLevelIds    = $levelMapper->findIdsByColumn('box_id', $submissionBox->getId());
        $existingDescriptors = DescriptorMapper::getInstance()->findByColumn('level_id', $existingLevelIds);

        // Spec for valid capability max marks
        $assessment = $grid->getAssessment();
        $markPerCap = $assessment instanceof DueWork\Model && $assessment->getMarkPerCapability();
        $spec       = new ValidCapabilityMax($markPerCap);

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


        // usages are a join table with a sequence, not a strict "object" - as such, we can purge the relevant records and simply re-create them
        $indicatorUsageMapper  = CriterionUsageMapper::getInstance();
        $capabilityUsageMapper = GroupUsageMapper::getInstance();
        $indicatorUsageModels  = $indicatorUsageMapper->createCollection();
        $capabilityUsageModels = $capabilityUsageMapper->createCollection();
        $descriptorModels      = $descriptorMapper->createCollection();
        $capabilitySequence    = 1;
        $indicatorUsageMapper->deleteByColumn('submission_box_id', $submissionBox->getId());
        $capabilityUsageMapper->deleteByColumn('submission_box_id', $submissionBox->getId());

        foreach ($grid->getCapabilityUsages() as $capabilityUsage) {

            // Validate cap against spec
            $capabilityMaxValue = $capabilityUsage->getMaxValue();
            if (!$spec->isSatisfiedBy($capabilityMaxValue)) {
                throw new \InvalidArgumentException(
                    'provide capability maximum points that are numeric and greater than or equal to 0'
                );
            }

            $capabilityUsageModel = $capabilityUsageMapper->createModel()
                ->setSubmissionBox($submissionBox)
                ->setGroup($capabilityModels[$capabilityUsage->getCapability()->getId()])
                ->setMaxValue($capabilityMaxValue)
                ->setSequence($capabilitySequence++);
            $capabilityUsageModels->add($capabilityUsageModel);

            $indicatorSequence = 1;
            foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
                $indicatorModel      = $indicatorModels[$indicatorUsage->getIndicator()->getId()];
                $indicatorUsageModel = $indicatorUsageMapper->createModel()
                    ->setSubmissionBox($submissionBox)
                    ->setCriterion($indicatorModel)
                    ->setSequence($indicatorSequence++);
                $indicatorUsageModels->add($indicatorUsageModel);

                // Descriptors
                foreach ($grid->getLevels() as $levelIndex => $level) {
                    $descriptor = $indicatorUsage->retrieveDescriptor($levelIndex);
                    if ($descriptor) {
                        $descriptorModel = $descriptorMapper->createModel()
                            ->setId($descriptor->getId())
                            ->setCriterion($indicatorModel)
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


        // invalidate changed descriptors
        $this->checkDescriptorValueChanges($existingDescriptors, $descriptorModels);


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
        $gridMarkScheme    = $markSchemeFactory->spawnMarkSchemeFor(
            $feedback->getAssessable(),
            $grid->calculateMaxValueFromCapabilities()
        );

        $markedGrid = new MarkedGrid($feedback, $grid, $gridMarkScheme);

        // What goes in the grid?
        // * Capability (fka group) marks
        // * Descriptor marks
        // ** more correctly known as indicator marks, which reference a descriptor
        /** @var GenericCollection|GroupMark[] $capabilityMarkModels */
        $capabilityMarkModels = GroupMarkMapper::getInstance()->findByColumn(
            'return_id',
            $feedback->getId()
        );

        /** @var Collection|GenericCollection[]|DescriptorMark[][] $indicatorMarkModelsByCapability */
        $indicatorMarkModelsByCapability = DescriptorMarkMapper::getInstance()->findByColumn(
            'rubric_group_mark_id',
            $capabilityMarkModels->getPropertyValues('id')
        )->groupByCallback(
            function (DescriptorMark $indicatorMark) {
                return $indicatorMark->getGroupMark()->getGroup()->getId();
            }
        );
        // Still only one selected descriptor per indicator
        $indicatorMarkModelsByCapability = $indicatorMarkModelsByCapability->map(
            function (GenericCollection $c) {
                return $c->indexByCallback(
                    function (DescriptorMark $mark) {
                        return $mark->getDescriptor()->getCriterion()->getId();
                    }
                );
            }
        );


        // Load em into the marked grid
        foreach ($capabilityMarkModels as $capabilityMarkModel) {
            // Load the capability
            $capabilityId = $capabilityMarkModel->getGroup()->getId();
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
            $capabilityMarkScheme = $markSchemeFactory->spawnMarkSchemeFor(
                $feedback->getAssessable(),
                $capabilityUsage->calculateMaxValueFromIndicators()
            );
            $capabilityMark = (new CapabilityMark($capabilityUsage, $capabilityMarkScheme))
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
     * @param AssessmentContext $context
     * @param string[]          $levelNames
     *
     * @return Rubric
     */
    public function createForContext(AssessmentContext $context, array $levelNames = [])
    {
        // Go one deeper to get unit and course
        if ($context instanceof Project) {
            return $this->createForContext($context->getBaseContext(), $levelNames);
        }

        $folderId       = $context->getFolder()->getId();
        $parentFolderId = null;

        if ($context instanceof UnitSpecification) {
            $parentFolderId = $context->getFolder()->getParent()->getId();
        }

        return $this->createForFolder($folderId, $parentFolderId, $levelNames);
    }

    /**
     * Generate a base rubric with Indicators (aka Criteria) taken from the specified Folder[/s]
     *
     * @deprecated use of ::createForContext() is preferable
     *
     * @param int      $folderId
     * @param int|null $parentFolderId
     * @param string[] $levelNames
     *
     * @return Rubric
     */
    public function createForFolder($folderId, $parentFolderId = null, array $levelNames = [])
    {
        /**
         * Indexed by normalised Capability name
         *
         * @var Criteria\Group[] $capabilityCache
         */
        $capabilityCache = [];

        /**
         * Indexed by normalised Capability name, then normalised Indicator name
         *
         * @var Criteria\Criterion $indicatorCache
         */
        $indicatorCache = [];

        $rubric = new Rubric();

        if (!count($levelNames)) {
            // default to 2 blank levels
            $levelNames = ['', ''];
        }

        // inject the levels
        foreach ($levelNames as $i => $level) {
            $rubric->putLevel(new CompetencyLevel($i, $level));
        }

        $args = [
            'folderId'   => $folderId,
            'parentType' => Group::PARENT_TYPE_CATEGORY,
        ];
        $in = ['folderId',];
        if ($parentFolderId) {
            $in[] = 'parentFolderId';
            $args['parentFolderId'] = $parentFolderId;
        }
        $in = implode(', :', $in);

        $stmt = $this->database->query("
            SELECT
                rc.id,
                rg.id AS group_id,
                rg.name AS grouping,
                rc.name,
                COUNT(a.id) AS usage_count,
                rg.parent_id
            FROM rubric_criterion rc
            INNER JOIN rubric_group           rg ON rg.id = rc.rubric_group_id
            LEFT JOIN  rubric_criterion_usage ru ON rc.id = ru.criterion_id
            LEFT JOIN  submission_box          b ON ru.submission_box_id = b.id
            LEFT JOIN  assessment              a ON b.assessment_id = a.id AND a.deleted_at IS NULL
            WHERE rg.parent_type = :parentType
            AND rg.parent_id IN (:$in)
            AND rc.deleted_at IS NULL
            AND rg.deleted_at IS NULL
            GROUP BY rc.id
        ", $args);

        // De-duplicate into caches, prioritising parent if duplicate between
        // folder + parentFolder (eg UnitSpec vs CourseSpec)
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            // Trim + collapse internal whitespace
            $capabilityNormalisedName = $this->normaliseForComparison($r['grouping']);

            // If first instance of Capability
            if (!array_key_exists($capabilityNormalisedName, $capabilityCache)) {
                // Save the Capability
                $capabilityCache[$capabilityNormalisedName] = new Criteria\Group(
                    $r['group_id'],
                    $this->normalizeSpace($r['grouping']),
                    null
                );

                // Instantiate Indicator cache for the Capability
                $indicatorCache[$capabilityNormalisedName] = [];
            }

            $indicatorNormalisedName = $this->normaliseForComparison($r['name']);

            // First time we've seen this Capability + Indicator, or Indicator
            // is from the parentFolder (higher priority) ?
            if (!isset($indicatorCache[$capabilityNormalisedName][$indicatorNormalisedName])
                || ($parentFolderId && $parentFolderId == $r['parent_id'])
            ) {
                $indicator = new Criteria\Criterion($r['id'], $this->normalizeSpace($r['name']));
                $indicator->setReadOnly($r['usage_count'] > 0);
                $indicatorCache[$capabilityNormalisedName][$indicatorNormalisedName] = $indicator;
            }
        }

        // For each group of Indicators
        foreach ($indicatorCache as $capabilityNormalisedName => $indicators) {

            // For each Indicator in the Capability
            foreach ($indicators as $indicatorNormalisedName => $indicator) {
                /** @var Criteria\Criterion $indicator */
                // cascade the read-onlyness up to the Capability
                if ($indicator->isReadOnly()) {
                    $capabilityCache[$capabilityNormalisedName]->setReadOnly(true);
                }
                $rubric->putCriterion($capabilityCache[$capabilityNormalisedName], $indicator);
            }
        }

        return $rubric;
    }

    /**
     * Given a box ID, return the rubric assigned to it, if any
     *
     * Unlike createForContext(), this is a data-level method (ie it works on data models).
     *
     * @param SubmissionBox $box
     * @param bool          $getParentCriteria - whether to include box's folder's parent's criteria
     * @param bool          $editMode          - whether to include criteria not in use on box
     *
     * @return Rubric
     */
    public function findBySubmissionBox(SubmissionBox $box, $getParentCriteria = false, $editMode = false)
    {
        list ($groups, $criteria) = $this->findCriteria($box, $getParentCriteria);
        $descriptors = $this->findDescriptors($box);
        $levelList   = $this->generateLevelList($box);

        // setup the data structure to match what the factory expects
        $postdata = [
            $box->getId() => [
                'level'      => $levelList,
                'group'      => $groups,
                'criterion'  => $criteria,
                'descriptor' => $descriptors,
            ],
        ];

        // use the factory to produce the rubric object
        /** @var Rubric $rubric */
        $rubric = $this->factory->buildFromPostData($postdata, $box->isMarkedPerRubricGroup(), $box->isMarkedUsingRange(), $getParentCriteria || $editMode);

        return $rubric->setSubmissionBox($box);
    }

    /**
     * Given a DueWork, return the rubric assigned to it, if any
     *
     * @param DueWork\Model $dueWork
     * @param bool          $getParentCriteria - whether to include box's folder's parent's criteria
     * @param bool          $editMode          - whether to include criteria not in use on box
     *
     * @return Rubric
     */
    public function findByDueWork(DueWork\Model $dueWork, $getParentCriteria = false, $editMode = false)
    {
        /** @var SubmissionBox $box */
        $box = SubmissionBoxMapper::getInstance()->findOneByColumn('assessment_id', $dueWork->getId());

        return $this->findBySubmissionBox($box, $getParentCriteria, $editMode);
    }

    /**
     * Find all criteria that are available or in use by the $box.
     *
     * We would wish to retrieve parent criteria when the $box lives in a UnitSpec, or project
     * in a UnitSpec. In these cases we need to also retrieve Criteria from the CourseSpec
     *
     * @param SubmissionBox $box
     * @param bool          $getParentCriteria - if specified, also get criteria the box's folder's parent
     *
     * @return array $return
     * @var array           $return            [0]                 Groups
     * @var array           $return            [0][]               A single Criteria-Group
     * @var string          $return            [0][]['name']       Name of the Criteria-Group
     * @var string          $return            [0][]['maxValue']   Maximum points value of the Criteria-Group
     * @var array           $return            [1]                 Criteria
     * @var array           $return            [1][]               A single Criterion
     * @var int             $return            [1][]['groupId']    The Criterion's Group's Id
     * @var bool            $return            [1][]['on']         Whether this criterion is selected in the $box
     * @var string          $return            [1][]['name']       Name of Criterion
     * @var bool            $return            [1][]['readonly']   Whether user should be prevented from changing name
     */
    protected function findCriteria(SubmissionBox $box, $getParentCriteria)
    {
        /** @var array $groups - indexed by groupId */
        $groups = [];

        /** @var array $criteria - indexed by criterion.id */
        $criteria = [];

        $criteriaStatement = $this->generateCriteriaStatement($box, $getParentCriteria);

        foreach ($criteriaStatement as $r) {

            $groupId     = $r['group_id'];
            $selected    = $r['selected'] > 0;
            $criterionId = $r['crit_id'];

            // First time we've encountered this group?
            // Add it to the list of groups to return
            if (empty($groups[$groupId])) {
                $maxValue               = isset($r['max_value']) ? (float)$r['max_value'] : null;
                $groups[$groupId]       = [
                    'name'     => $r['grouping'],
                    'maxValue' => $selected ? $maxValue : null,
                ];
            }

            // First time we've encountered this criterion?
            // Add it to the list of criteria to return
            if (empty($criteria[$criterionId])) {
                $usedElsewhere          = ($r['usages'] - $r['selected']) > 0;
                $criteria[$criterionId] = [
                    'groupId'  => $groupId,
                    'on'       => $selected,
                    'name'     => $r['name'],
                    'readonly' => $usedElsewhere,
                ];
            }
        }

        return [$groups, $criteria];
    }

    /**
     * Find all criteria that are available for the $box.
     *
     * For each criterion, also get:
     * * how many non-deleted boxes are using the criteria,
     * * whether the specified box is using the criteria
     *
     * Sort criteria by:
     * * whether they are in use by this box,
     * * if in use, by sequence, else by name
       *
     * Note: A criterion being used by a soft-deleted assessment does not count towards current usages, and hence
     *       does not prevent the user from editing the criterion's name, or soft-deleting the criterion itself.
     *
     * @param SubmissionBox $box
     * @param bool          $getParentCriteria - whether to also get criteria from box's folder's parent
     *
     * @throws \Schoolbox\Mapper\Exception
     * @return array
     */
    protected function generateCriteriaStatement(SubmissionBox $box, $getParentCriteria)
    {
        $args = [
            'boxId'      => $box->getId(),
            'folderId'   => $box->getCategory()->getId(),
            'parentType' => Group::PARENT_TYPE_CATEGORY,
        ];

        $in = ['folderId'];

        // Retrieve criteria from the box's folder's parent ?
        if ($getParentCriteria) {

            // Is parent not yet hydrated?
            if (!$box->getCategory()->getParent()) {
                $box->setCategory(FolderMapper::getInstance()->findCheaplyById($box->getCategory()->getId()));
            }

            $key        = 'parentFolderId';
            $args[$key] = $box->getCategory()->getParent()->getId();
            $in[]       = $key;
        }

        $in = implode(', :', $in);

        $criteriaStatement = $this->database->query("
            SELECT
                sb.id AS box_id,
                rc.id AS crit_id,
                rg.id AS group_id,
                rg.name AS grouping,
                rc.name,
                rgu.max_value AS max_value,
                -- Only get sequence for the requested box (don't care about other usages)
                IF(sb.id = :boxId, rcu.sequence, NULL) AS sequence
            FROM rubric_criterion rc
            INNER JOIN rubric_group           rg  ON rg.id = rc.rubric_group_id
            LEFT JOIN  rubric_criterion_usage rcu ON rcu.criterion_id = rc.id
            LEFT JOIN  submission_box         sb  ON rcu.submission_box_id = sb.id
            LEFT JOIN  assessment             a   ON sb.assessment_id = a.id AND a.deleted_at IS NULL
            LEFT JOIN  rubric_group_usage     rgu ON rgu.rubric_group_id = rg.id AND rgu.submission_box_id = sb.id
            WHERE rg.parent_type = :parentType
            AND rg.parent_id IN (:$in)
            AND rc.deleted_at IS NULL
            AND a.deleted_at IS NULL
            AND rg.deleted_at IS NULL
            ORDER BY sequence DESC
        ", $args);

        // Group result by criterion, and track which boxes use the criterion
        $result = [];
        while ($row = $criteriaStatement->fetch(PDO::FETCH_ASSOC)) {
            $criterionId = (int)$row['crit_id'];
            if (!isset($result[$criterionId])) {
                $result[$criterionId] = $row;
            }

            $result[$criterionId]['box_ids'][] = (int)$row['box_id'];
        }
        $criteriaStatement->closeCursor();

        // Add these fields in post-processing:
        // * selected: is this criterion used by $box?
        // * usages:   how many boxes (including $box) use this criterion?
        foreach ($result as $criterionId => $row) {
            $result[$criterionId]['selected'] = \in_array($box->getId(), $row['box_ids'], true);
            $result[$criterionId]['usages']   = \count($row['box_ids']);
        }

        return $result;
    }

    /**
     * @param SubmissionBox $box
     *
     * @return array
     */
    protected function findDescriptors(SubmissionBox $box)
    {
        $levelIds = LevelMapper::getInstance()->findIdsByColumn('box_id', $box->getId());

        /** @var Collection|Descriptor[] $descriptorCollection */
        $descriptorCollection = DescriptorMapper::getInstance()->findByColumnList(['level_id' => $levelIds]);

        $descriptors = [];

        foreach ($descriptorCollection as $d) {
            $descriptors[$d->getCriterion()->getId()][$d->getLevel()->getSequence()] = [
                'id'     => $d->getId(),
                'text'   => $d->getDescription(),
                'points' => $d->getMaxValue(),
            ];
        }

        return $descriptors;
    }

    /**
     * @param SubmissionBox $box
     *
     * @return array
     */
    protected function generateLevelList(SubmissionBox $box)
    {
        $levelList = [];

        $levelCollection = LevelMapper::getInstance()->findByColumn('box_id', $box->getId(), ['order' => ['sequence' => 'ASC']]);

        foreach ($levelCollection as $level) {
            $levelList[$level->getSequence()] = [
                'id'   => $level->getId(),
                'name' => $level->getName(),
            ];
        }

        // ensure there are ALWAYS at least 2 levels
        while (count($levelList) < 2) {
            $levelList[] = ['name' => ''];
        }

        return $levelList;
    }

    /**
     * Convenience (or safety) method wrapping grid fetch and mark application
     *
     * @param SubmissionBox $box
     * @param User          $student
     *
     * @return Rubric
     */
    public function findBySubmissionBoxAndStudent(SubmissionBox $box, User $student)
    {
        $grid = $this->findBySubmissionBox($box);

        return (new GridGenerator($this, $grid, $student))->applyInstructorGivenMark(true);
    }

    /**
     * Normalises spaces.
     *
     * Trims trailing/leading space, and replaces multiple spaces/newlines/etc.
     * with a single space.
     *
     * @param  string $string
     *
     * @return string
     */
    protected function normalizeSpace($string)
    {
        return trim(preg_replace('`\s+`', ' ', $string));
    }

    /**
     * Normalize encoding of a cell from the uploaded CSV.
     *
     * `$string` _should_ be UTF-8, but the CSV could've been saved in another
     * encoding. (So far, we only recognise Windows-1252)
     *
     * @param string $string
     * @return string
     */
    protected function normalizeEncoding(string $string): string
    {
        // Assume $string is utf-8 encoded, unless proven otherwise
        // If it isn't, assume it's Windows-1252
        $encoding = mb_detect_encoding($string, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);

        switch ($encoding) {
            case 'Windows-1252':
            case 'CP1252':
            case 'ISO-8859-1':
                // All the CP1252 test strings I used detected as ISO-8859-1.
                // You actually want CP1252
                return mb_convert_encoding($string, 'UTF-8', 'CP1252');

            case 'UTF-8':
            default:
                return $string;
        }
    }

    /**
     * Converts a Criterion name for comparison. Do NOT use for mutation before storage.
     *
     * * converts to lowercase
     * * Replaces multiple whitespace with single space character
     * * trims whitespace
     *
     * @param $string
     *
     * @return string
     */
    public function normaliseForComparison($string)
    {
        return strtolower($this->normalizeSpace($string));
    }

    protected function getcsv($fp, $limit = 13)
    {
        $line = fgetcsv($fp);

        if (is_null($line)) {
            // EOF
            return null;
        }

        if (is_array($line)) {
            // normal data
            // Normalize line values
            return array_map(
                [$this, 'normalizeEncoding'],
                array_map(
                    [$this, 'normalizeSpace'],
                    \array_slice($line, 0, $limit)
                )
            );
        }

        // bad data, but not EOF
        return [];
    }

    /**
     * @param IStorage $file
     * @param int      $folderId
     * @param int      $maxLevels
     *
     * @throws \Schoolbox\Resource\Exception
     * @return array 0: rubric POST data used to build a rubric grid
     *               1: bool: does the rubric have customised max mark per group values?
     *                        (required as the rubric object doesn't store this separately
     *                         from their actual value)
     *               2: bool: does the rubric have mark ranges switched on?
     */
    public function buildFromCSV(IStorage $file, $folderId, $maxLevels): array
    {
        $square = [];

        // Determine if the specified folder has a UnitSpec
        /** @var UnitSpecification $unitSpec */
        $unitSpec = UnitSpecificationMapper::getInstance()->findByColumn('folder_id', $folderId)->first();
        $courseSpecFolderId = null;
        if ($unitSpec) {
            // Add the Unit's Course's Folder's ID
            $courseSpecFolderId = $unitSpec->getCourse()->getFolder()->getId();
        }

        $fp = $file->readStream();

        $headerRow = $this->getcsv($fp, $maxLevels + 2);

        // must be an array, and must have at least 3 "cells"
        if (!is_array($headerRow) || count($headerRow) < 3) {
            throw new \Schoolbox\Resource\Exception("Not a valid rubric CSV file");
        }

        // First two cells may be settings:
        // useMarkRange:      On/Off
        // markPerCapability: On/Off
        $useMarkRange      = false;
        $markPerCapability = false;
        $settingCells      = [$headerRow[0], $headerRow[1]];
        foreach ($settingCells as $settingCell) {
            [$setting, $value] = explode(':', $settingCell);
            switch (strtolower(trim($value))) {
                case '1':
                case 'yes':
                case 'on':
                    $boolValue = true;
                    break;

                case '0':
                case 'no':
                case 'off':
                default:
                    $boolValue = false;
                    break;
            }
            switch (trim($setting)) {
                case 'useMarkRange':
                    $useMarkRange = $boolValue;
                    break;

                case 'markPerCapability':
                    $markPerCapability = $boolValue;
                    break;

                default:
                    break;
            }
        }

        // Note: this used to be able to tell if the first row was actually the
        // first group, instead of the set of levels. (This would happen if
        // Excel saved a CSV with nothing at all in the first row.)
        // Because the first two cells are now reserved for settings, they
        // should never be empty anymore; however, we can't tell anymore if the
        // spreadsheet used to have an empty first row.
        // (This was raised in SB-12175)

        $square[] = $headerRow;

        // list of levels
        $levelNames = array_slice($square[0], 2);

        $workingGroupName = '';
        $workingCritName = '';

        // build the square, extracting data as we go
        $groups            = [];
        $isLastRowNewGroup = false;
        while ($row = $this->getcsv($fp, $maxLevels + 2)) {
            if ($isLastRowNewGroup) {
                $groupMaxValue     = $row[0];
                $isLastRowNewGroup = false;
            } else {
                // list of criteria, grouped by... uh, group
                if ($row[0]) {
                    $workingGroupName  = $row[0];
                    $isLastRowNewGroup = true;
                }
            }

            // copy the groupname into the row
            $row[0] = $workingGroupName;

            if ($row[1]) {
                $workingCritName = $row[1];
            }
            // copy the criteria name into the row
            $row[1] = $workingCritName;

            // extract the criterion uniquely (can be blank)
            // (The group values persist from previous rows)
            $groups[$workingGroupName]['id']                         = null;
            $groups[$workingGroupName]['name']                       = $workingGroupName;
            $groups[$workingGroupName]['maxValue']                   = $groupMaxValue ?: null;
            $groups[$workingGroupName]['criteria'][$workingCritName] = [
                'id'   => null,
                'name' => $workingCritName,
            ];

            $square[] = $row;
        }

        // ensure all given criteria exist: create new ones if necessary
        $criterionMapper = CriterionMapper::getInstance();
        foreach ($groups as $groupName => $group) {
            foreach ($group['criteria'] as $crit) {
                $criterionName = $crit['name'];
                $model         = null;

                // Which criterion is used?
                try {
                    // If there's a matching criterion in the folder: use it
                    $model = $criterionMapper->findOneForFolderByKey($folderId, $criterionName, $groupName);

                } catch (MapperException $e) {
                    // If there isn't:
                    // * If there's a course spec: use a matching criterion from it
                    if ($courseSpecFolderId) {
                        try {
                            $model = $criterionMapper->findOneForFolderByKey($courseSpecFolderId, $criterionName, $groupName);
                        } catch (MapperException $e) {
                            // There's no match in the folder or spec.
                            // Create a new criterion in the folder
                        }
                    }
                }

                // If there's no course spec, or there's no match in the spec:
                // * create a new criterion in the folder
                if (!$model) {
                    $model = $criterionMapper->findOrCreateOneForFolderByKey($folderId, $criterionName, $groupName);
                }

                // yank those IDs
                $groups[$groupName]['id']                             = $model->getGroup()->getId();
                $groups[$groupName]['criteria'][$criterionName]['id'] = $model->getId();
            }

        }

        // get a new rubric in array form
        $postdata = $this->createForFolder($folderId, $courseSpecFolderId, $levelNames)->toPostData();

        // overlay descriptor and max points data
        for ($i = 1; $i < count($square); $i += 2) {

            $gLookup = $square[$i][0];
            $cLookup = $square[$i][1];

            $crit = $groups[$gLookup]['criteria'][$cLookup];

            if (!$crit) {
                // given the way we're working with data we've expressly set up just above, this should be impossible...
                continue;
            }

            $groupId = $groups[$gLookup]['id'];
            $critId  = $crit['id'];

            for ($j = 2; $j < count($square[$i]); ++$j) {

                $levelSequence = $j - 2;

                $dText = trim($square[$i][$j]);
                $dVal  = trim($square[$i + 1][$j]);

                if (strlen($dText) || strlen($dVal)) {

                    // Set the group's max value
                    $postdata['group'][$groupId]['maxValue'] = $groups[$gLookup]['maxValue'];

                    // turn this criterion on
                    $postdata['criterion'][$critId]['on'] = true;

                    // assign the descriptor data to the appropriate cell
                    $postdata['descriptor'][$critId][$levelSequence] = [
                        'id'     => null,
                        'text'   => $dText,
                        'points' => $dVal,
                    ];
                }
            }
        }


        // For each group: infer its mark-per-capability
        if ($markPerCapability) {
            foreach ($postdata['group'] as $groupId => $group) {
                // Only infer if it's not provided
                if (isset($group['maxValue'])) {
                    continue;
                }

                // Find the criteria for this group
                $inferredMaxValue = 0;
                foreach ($postdata['criterion'] as $criterionId => $criterion) {
                    if ((int)$criterion['groupId'] !== $groupId) {
                        continue;
                    }


                    // Now go thru its descriptors
                    $criterionMaxValue = 0;
                    foreach ($postdata['descriptor'][$criterionId] as $descriptor) {
                        $criterionMaxValue = max($criterionMaxValue, $descriptor['points']);
                    }

                    $inferredMaxValue += $criterionMaxValue;
                }

                $postdata['group'][$groupId]['maxValue'] = $inferredMaxValue;
            }
        }


//        /** @var Rubric $rubric */
//        // TODO until such time as mark range config is stored in CSV, default to false
//        $rubric = $this->factory->buildFromPostData([0 => $postdata], $markPerCapability, $useMarkRange,true);

        return [$postdata, $markPerCapability, $useMarkRange];
    }

    /**
     * Save rubrics selections for a particular feedback.
     *
     * @param array[] $descriptors key = descriptor.id
     *                             val = [selected: is it selected?, value: what's its value?]
     * @param array[] $customGroupMarks      key = group.id
     *                             val = [value: what's its value? (only if set manually)]
     * @param IMarkScheme $markScheme
     * @param int     $returnId    Feedback
     * @throws \InvalidArgumentException
     */
    public function saveForFeedback(array $descriptors, array $customGroupMarks, IMarkScheme $markScheme, int $returnId): void
    {
        $groupMapper     = GroupMapper::getInstance();
        $descriptorMapper     = DescriptorMapper::getInstance();

        $descriptorMarkMapper = DescriptorMarkMapper::getInstance();
        $groupMarkMapper      = GroupMarkMapper::getInstance();
        $returnedWorkMapper   = SubmissionBox\ReturnedWorkMapper::getInstance();

        // Blow away any previous descriptor records with the feedback
        $descriptorMarkMapper->deleteFeedbackMarks($returnId);
        $groupMarkMapper->deleteFeedbackMarks($returnId);

        $groupMarksToSave      = $groupMarkMapper->createCollection();
        $descriptorMarksToSave = $descriptorMarkMapper->createCollection();

        // What actually got saved? We may have:
        // - optionally, marks from the custom mark-per-cap input fields (only if mark-per-cap is enabled. may be empty array)
        // - optionally, selected descriptor values (may be empty array)

        $selectedDescriptors = [];
        foreach ($descriptors as $id => $descriptor) {
            if (!empty($descriptor['selected'])) {
                $selectedDescriptors[$id] = $descriptor['value'];
            }
        }

        $returnedWork     = $returnedWorkMapper->findById($returnId);

        // Pass 1.5: is a custom max mark for the group set?
        foreach ($customGroupMarks as $groupId => $markData) {

            // TODO I should be validating against unscaled mark, not custom mark
            if ((new ValidCapabilityMark($markData['max'], $markScheme))->isSatisfiedBy($markData['customMark'])) {
                // Find a record for its group
                $group = $groupMapper->findById($groupId);

                // If it doesn't exist: make a new one
                // TODO under what circumstance would it be possible to mark a group that doesn't exist??
                if (!$groupMarksToSave[$groupId]) {
                    $groupMarksToSave[$groupId] = $groupMarkMapper->createModel()
                        ->setSubmissionReturn($returnedWork)
                        ->setGroup($group)
                        ->setValid(true);
                }

                // Save the unscaled mark if provided; otherwise just 0
                // TODO confirm this w/ d-baker
                $groupMarksToSave[$groupId]->setValue($markData['unscaledMark']);
            } else {
                // TODO handle this somewhere else?
                throw new \InvalidArgumentException("Capability mark must be between 0 and " . $markData['max']);
            }
        }

        // Pass 2: build set of descriptor marks
        foreach ($selectedDescriptors as $descriptorId => $value) {

            // If this isn't set: this is an issue
            $descriptor = $descriptorMapper->findById($descriptorId);

            // Find the record for its group
            $groupId = $descriptor->getCriterion()->getGroup()->getId();

            // Save the descriptor group
            // Take value from form preferentially, then descriptor
            $savedValue = $value ?? $descriptor->getMaxValue();
            $descriptorMarksToSave->add(
                $descriptorMarkMapper->createModel()
                    ->setGroupMark($groupMarksToSave[$groupId])
                    ->setDescriptor($descriptor)
                    ->setValid(true)
                    ->setValue($savedValue)
            );
        }

        // Save marks for groups as well
        $groupMarkMapper->save($groupMarksToSave);
        $descriptorMarkMapper->save($descriptorMarksToSave);
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
        $groupMarkMapper            = GroupMarkMapper::getInstance();
        $descriptorMarkMapper       = DescriptorMarkMapper::getInstance();
        $sourceDescriptorMarks      = $descriptorMarkMapper->findByColumn('g.return_id', $sourceReturnId);
        $destinationGroupMarks      = $groupMarkMapper->createCollection();
        $destinationDescriptorMarks = $descriptorMarkMapper->createCollection();
        $destinationReturn          = SubmissionBox\ReturnedWorkMapper::getInstance()->createModel($destinationReturnId);

        foreach ($sourceDescriptorMarks as $sourceDescriptor) {

            // Is this group marks set?
            $sourceGroupMark = $sourceDescriptor->getGroupMark();
            $group           = $sourceGroupMark->getGroup();
            $groupId         = $group->getId();
            if (!$destinationGroupMarks[$groupId]) {
                $destinationGroupMarks[$groupId] = $groupMarkMapper->createModel()
                    ->setGroup($group)
                    ->setSubmissionReturn($destinationReturn)
                    ->setValue($sourceGroupMark->getValue())
                    ->setValid($sourceGroupMark->isValid());
            }

            $destinationDescriptorMarks->add(
                $descriptorMarkMapper->createModel()
                    ->setGroupMark($destinationGroupMarks[$groupId])
                    ->setDescriptor($sourceDescriptor->getDescriptor())
//                    ->setSubmissionReturn($destinationReturn)
                    ->setValue($sourceDescriptor->getValue())
                    ->setValid($sourceDescriptor->isValid())
            );
        }
        $groupMarkMapper->save($destinationGroupMarks);
        $descriptorMarkMapper->save($destinationDescriptorMarks);
    }

    /**
     * @param Rubric        $rubric
     * @param SubmissionBox $submissionBox
     */
    public function save(Rubric $rubric, SubmissionBox $submissionBox)
    {
        // juuust in case
        $rubric->setSubmissionBox($submissionBox);

        // TODO some way to wrap all this in a transaction would be nice... -_-;
        $existingLevelIds    = LevelMapper::getInstance()->findIdsByColumn('box_id', $submissionBox->getId());
        $existingDescriptors = DescriptorMapper::getInstance()->findByColumn('level_id', $existingLevelIds);
        $existingDescriptors = $existingDescriptors->map(function (Descriptor $d) {
            return clone $d;
        });

        // first, persist the row and column headers (criteria and levels, respectively)
        GroupMapper::getInstance()->save($rubric->getORMGroups());
        CriterionMapper::getInstance()->save($criteria = $rubric->getORMCriteria());
        LevelMapper::getInstance()->save($levels = $rubric->getORMLevels());

        // usages are a join table with a sequence, not a strict "object" - as such, we can purge the relevant records and simply re-create them
        // TODO consider removing submission_box_id, associating with group ID instead
        CriterionUsageMapper::getInstance()->deleteByColumn('submission_box_id', $submissionBox->getId());
        CriterionUsageMapper::getInstance()->save($usages = $rubric->getORMCriterionUsages());

        // ditto group usages
        GroupUsageMapper::getInstance()->deleteByColumn('submission_box_id', $submissionBox->getId());
        GroupUsageMapper::getInstance()->save($rubric->getORMGroupUsages());

        // finally, now that everything else is saved, save the descriptors
        $descIds = DescriptorMapper::getInstance()->save($descs = $rubric->getORMDescriptors());

        $this->checkDescriptorValueChanges($existingDescriptors, $descs);

        // wash up before dinner, kids

        // remove all levels that have zero descriptors attached - extract from the descriptors
        $seenLevels = [];
        foreach ($descs as $desc) {
            $seenLevels[$desc->getLevel()->getId()] = true;
        }
        $seenLevels = array_keys($seenLevels);

        $this->tidyUp(LevelMapper::getInstance(), ['box_id' => $submissionBox->getId()], $seenLevels);

        // remove all descriptors that are on this box, but are not among the descriptors we just saved (it indicates blankness / unused-ness)
        $levelIds = LevelMapper::getInstance()->findIdsByColumn('box_id', $submissionBox->getId(), ['deleted' => true]);
        $this->tidyUp(DescriptorMapper::getInstance(), ['level_id' => $levelIds], $descIds);
    }

    /**
     * Soft-delete any rubric levels used by the specified submission boxes if they
     * no longer contain any non-deleted descriptors.
     *
     * Returns the count of levels soft-deleted.
     *
     * @param int[] $submissionBoxIds
     *
     * @return int
     */
    public function deleteEmptyLevels(array $submissionBoxIds)
    {
        if (!count($submissionBoxIds)) {
            return 0;
        }

        $levelMapper       = LevelMapper::getInstance();
        $pb                = $levelMapper->getPredicateBuilder()->in('rl.box_id', $submissionBoxIds);
        $boxIdPlaceholders = $pb->getWhere()[0];

        $query = "
            SELECT rl.id FROM rubric_level rl
            LEFT JOIN (SELECT id, level_id FROM rubric_descriptor WHERE deleted_at IS NULL) rd ON rd.level_id = rl.id
            WHERE rd.id IS NULL
            AND rl.deleted_at IS NULL
            AND {$boxIdPlaceholders};
        ";

        $statement = $this->database->query($query, $pb->getParams());
        $levelIds  = $statement->fetchAll(PDO::FETCH_COLUMN);

        return $levelMapper->deleteByColumn($levelMapper->getIdentifierColumn(), $levelIds);
    }

    /**
     * Invalidates selected student marks if already given for this rubric and the rubric has changed.
     *
     * * If an existing descriptor has had its value changed then invalidate all marks for the rubric.
     * * If an indicator is added, do nothing.
     * * If an indicator is removed, invalidate marks for that indicator.
     *
     * @param Collection|Descriptor[] $existingDescriptors
     * @param Collection|Descriptor[] $newDescriptors The newly saved descriptors
     *
     * @return bool Did we invalidate any descriptors?
     */
    protected function checkDescriptorValueChanges(Collection $existingDescriptors, Collection $newDescriptors)
    {
        // Group descriptors by criterionId, then LevelId, to enable O(1) lookup
        $criteriaCallback = function (Descriptor $descriptor) {
            return $descriptor->getCriterion()->getId();
        };
        /** @var Collection|Descriptor[][] $existingCriteriaDescriptors */
        $existingCriteriaDescriptors = $existingDescriptors->groupByCallback($criteriaCallback);
        /** @var Collection|Descriptor[][] $newCriteriaDescriptors */
        $newCriteriaDescriptors = $newDescriptors->groupByCallback($criteriaCallback);

        $levelsCallback = function (Descriptor $descriptor) {
            return $descriptor->getLevel()->getId();
        };
        foreach ($existingCriteriaDescriptors as $existingCriterionDescriptors) {
            $existingCriterionDescriptors->indexByCallback($levelsCallback);
        }
        foreach ($newCriteriaDescriptors as $newCriterionDescriptors) {
            $newCriterionDescriptors->indexByCallback($levelsCallback);
        }

        // Have any descriptor values been updated ? (but not completely deleted)
        $hasDescriptorValuesChanged = false;
        $deletedDescriptors         = DescriptorMapper::getInstance()->createCollection();
        foreach ($existingCriteriaDescriptors as $criterionId => $existingCriterionDescriptors) {
            /** @var Collection $newCriterionDescriptors */
            $newCriterionDescriptors = $newCriteriaDescriptors->getOffset($criterionId);

            // Have we deleted this criterion?
            if (!$newCriterionDescriptors) {
                // Make a note of them, we may need you later...
                $deletedDescriptors = $deletedDescriptors->merge($existingCriterionDescriptors);
                continue;
            }

            // Examine each descriptor in this criterion
            foreach ($existingCriterionDescriptors as $levelId => $existingDescriptor) {
                /** @var Descriptor $newDescriptor */
                $newDescriptor = $newCriterionDescriptors->getOffset($levelId);

                // Has the descriptor been deleted?
                if (!$newDescriptor) {
                    $hasDescriptorValuesChanged = true;
                    break;
                }

                // Has the value changed ?
                $existingValue = $existingDescriptor->getMaxValue();
                $newValue      = $newDescriptor->getMaxValue();
                if ($existingValue != $newValue) {
                    $hasDescriptorValuesChanged = true;
                    break;
                }
            }
        }

        // Did we detect a value change?
        if ($hasDescriptorValuesChanged) {
            $this->invalidateDescriptorMarks($existingDescriptors);

            return true;
        }

        // Did we delete any criterion?
        if ($deletedDescriptors->count()) {
            $this->invalidateDescriptorMarks($deletedDescriptors);

            return true;
        }

        return false;
    }

    /**
     * Invalidates all marks given for the specified descriptors
     *
     * @param Collection|Descriptor[] $descriptors
     */
    protected function invalidateDescriptorMarks(Collection $descriptors)
    {
        $markMapper    = DescriptorMarkMapper::getInstance();
        $descriptorIds = $descriptors->getColumnValues('id');
        $columnList    = [
            'descriptor_id' => $descriptorIds,
            'valid'         => true,
        ];
        $marks         = $markMapper->findByColumnList($columnList);
        foreach ($marks as $mark) {
            $mark->setValid(false);
        }
        $markMapper->save($marks);
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
                    /** @var Descriptor|Level $model */
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
     * @param GenericCollection|DueWork\Model[] $dueWorks
     * @param User                              $student
     *
     * @return array $longitude
     * @var array                               $longitude [$capability]
     * @var string                              $longitude [$capability]['name']
     * @var array                               $longitude [$capability][$indicator]
     * @var string                              $longitude [$capability][$indicator]['name']
     * @var array                               $longitude [$capability][$indicator]['activities']
     * @var array                               $longitude [$capability][$indicator]['activities'][$assessmentId]
     * @var int                                 $longitude [$capability][$indicator]['activities'][$assessmentId]['id']
     * @var string                              $longitude [$capability][$indicator]['activities'][$assessmentId]['name']
     * @var string                              $longitude [$capability][$indicator]['activities'][$assessmentId]['descriptor']
     */
    public function getLongitudinal(GenericCollection $dueWorks, User $student)
    {
        if (0 === $dueWorks->count()) {
            return [];
        }

        $pb = new Builder($this->database, '');

        $pb
            ->in('sb.assessment_id', $dueWorks->getPropertyValues('id'))
            ->equals('sr.owner', $student->getId())
            ->equals('sr.review_route', SubmissionBox\ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->notNull('sr.published_at')
            ->isTrue('rgm.valid')
            ->isNull('sr.deleted_at')
            ->isNull('rd.deleted_at')
            ->isNull('rl.deleted_at')
            ->isNull('rc.deleted_at')
            ->isNull('rg.deleted_at')
            ->isNull('a.deleted_at');

        $where = implode(' AND ', $pb->getWhere());

        // While MariaDB appears to return the desired value in a column not in a group aggregate function, this
        // behaviour is NOT guaranteed, the value in such cases is officially indeterminate.

        // We have two implementation options here:
        // 1. Get more results than we really want, sort in order so that the last per group is
        //    the only one we want, then discard some results during processing, or
        // 2. More complex query to only get results we want

        // While the former is simpler, it's too fragile and will break if someone changes the sorting.
        // The below query is complex, but is the standard pattern:
        // see http://stackoverflow.com/questions/7745609/sql-select-only-rows-with-max-value-on-a-column

        $stmt = $this->database->prepare("
            SELECT rg.name AS grouping, rc.name, a.id, a.title, rd.description
            FROM assessment              a
            JOIN submission_box         sb ON  a.id = sb.assessment_id
            JOIN rubric_level           rl ON sb.id = rl.box_id
            JOIN rubric_descriptor      rd ON rl.id = rd.level_id
            JOIN rubric_criterion       rc ON rc.id = rd.criterion_id
            JOIN rubric_group           rg ON rg.id = rc.rubric_group_id
            JOIN rubric_group_mark     rgm ON rg.id = rgm.rubric_group_id
            JOIN submission_return      sr ON sr.id = rgm.return_id
            JOIN (
                SELECT a.id AS 'a.id', rc.id AS 'rc.id', max(sr.published_at) AS 'sr.max_published_at'
                FROM rubric_descriptor_mark rm
                JOIN rubric_descriptor      rd ON rd.id = rm.descriptor_id
                JOIN rubric_criterion       rc ON rc.id = rd.criterion_id
                JOIN rubric_group           rg ON rg.id = rc.rubric_group_id
                JOIN rubric_level           rl ON rl.id = rd.level_id
                JOIN submission_box         sb ON sb.id = rl.box_id
                JOIN assessment              a ON  a.id = sb.assessment_id
                JOIN rubric_group_mark     rgm ON rg.id = rgm.rubric_group_id
                JOIN submission_return      sr ON sr.id = rgm.return_id
                WHERE {$where}
                GROUP BY sb.assessment_id, rd.criterion_id
            ) t ON `a`.`id` = `t`.`a.id` AND `rc`.`id` = `t`.`rc.id` AND `sr`.`published_at` = `t`.`sr.max_published_at`
            WHERE {$where}
            ORDER BY rg.name ASC, rc.name ASC, a.due_date DESC;
        ");

        $stmt->execute($pb->getParams());

        $longitude = [];

        while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {

            list ($capability, $indicator, $assessmentId, $assessmentTitle, $descriptor) = $r;

            $longitude[$capability]['name']                                                = $capability;
            $longitude[$capability]['indicators'][$indicator]['name']                      = $indicator;
            $longitude[$capability]['indicators'][$indicator]['activities'][$assessmentId] = [
                'id'         => $assessmentId,
                'name'       => $assessmentTitle,
                'descriptor' => $descriptor,
            ];
        }

        return $longitude;
    }

    /**
     * Copies a Rubric from source DueWork to destination DueWork.
     *
     * Overall logic is to build the source rubric object graph, then clone and clear all IDs.
     *
     * If during traversal we detect that the destination folder already has a criterion with the same
     * name, we use the destination criterion ID to avoid insertion of a duplicate.
     *
     * Logically:
     * * Levels are copied from srcBox to dstBox
     * * Descriptors are copied from srcBox to dstBox
     * * Criterion only copied from src folder to dst folder (if not already present on dst)
     * * Usages are copied from srcBox + srcCriterion to dstBox + dstCriterion
     *
     * @param int $sourceAssessmentId
     * @param int $destinationAssessmentId
     *
     * @return bool Whether a new Rubric was created.
     */
    public function copyRubric($sourceAssessmentId, $destinationAssessmentId)
    {
        // TODO extract this entire monstrosity elsewhere

        // Get required mappers
        $boxMapper            = SubmissionBoxMapper::getInstance();
        $levelMapper          = LevelMapper::getInstance();
        $descriptorMapper     = DescriptorMapper::getInstance();
        $groupMapper          = GroupMapper::getInstance();
        $criterionMapper      = CriterionMapper::getInstance();
        $groupUsageMapper     = GroupUsageMapper::getInstance();
        $criterionUsageMapper = CriterionUsageMapper::getInstance();

        $sourceBox = $boxMapper->findOneByColumn('assessment_id', $sourceAssessmentId);

        /** @var Level[]|GenericCollection $srcLevels */
        $srcLevels = $levelMapper->findByColumn('box_id', $sourceBox->getId())->indexBy('id');

        // If no levels, the source box does not have a rubric. Bug out.
        if (!count($srcLevels)) {
            return false;
        }

        $dstBox = $boxMapper->findOneByColumn('assessment_id', $destinationAssessmentId);

        // Cannot mutate identity of objects returned from mappers.
        // Index all these collections by source version ID, even though the object will be destination version.
        /** @var Level[]|GenericCollection $dstLevels */
        $dstLevels = $levelMapper->createCollection();
        /** @var Descriptor[]|GenericCollection $dstDescriptors */
        $dstDescriptors = $descriptorMapper->createCollection();
        /** @var Group[]|GenericCollection $dstGroups */
        $dstGroups = $groupMapper->createCollection();
        /** @var Criterion[]|GenericCollection $dstCriteria */
        $dstCriteria = $criterionMapper->createCollection();
        /** @var Group[]|GenericCollection $dstGroups */
        $dstGroupUsages = $groupUsageMapper->createCollection();
        /** @var CriterionUsage[]|GenericCollection $dstCriterionUsages */
        $dstCriterionUsages = $criterionUsageMapper->createCollection();

        // Copy Levels
        foreach ($srcLevels as $srcLevel) {
            $dstLevel = clone $srcLevel;
            $dstLevel->setId(null);
            $dstLevel->setSubmissionBox($dstBox);
            $dstLevel->setDescriptors($descriptorMapper->createCollection());
            $dstLevels->setOffset($srcLevel->getId(), $dstLevel);
        }

        // Get Source Descriptors
        /** @var Descriptor[]|GenericCollection $srcDescriptors */
        $srcDescriptors = $descriptorMapper->findByColumn('level_id', $srcLevels->getColumnValues('id'))->indexBy('id');
        $srcCriteria    = $criterionMapper->createCollection();
        $srcGroups      = $groupMapper->createCollection();

        /* Get existing destination criteria.
         *
         * If during destination object graph build, we detect that a source descriptor
         * already exists on destination, use the destination version instead of creating a new criterion
         *
         * For now, need to by-pass cache, as we may have inserted criteria during previous call to this function.
         * //TODO - the mappers should be invalidating the SELECT cache on INSERT/UPDATE @see SA-1951
         */
        /** @var Descriptor $firstSrcDescriptor */
        $firstSrcDescriptor = $srcDescriptors->first();
        $existingCriteria   = $criterionMapper->findForParent(
            $dstBox->getCategory()->getId(),
            $firstSrcDescriptor->getCriterion()->getGroup()->getParentType(),
            false
        );
        // Convert to 2-D arrays, indexed by 'group' then 'name', so we can later find duplicates.
        /** @var Criterion[][] $existingCriterionMap */
        $existingCriterionMap = [];
        /** @var Group[] $existingGroupMap */
        $existingGroupMap = [];
        foreach ($existingCriteria as $existingCriterion) {
            $groupName                           = $this->normaliseForComparison($existingCriterion->getGroup()->getName());
            $criterionName                       = $this->normaliseForComparison($existingCriterion->getName());
            $existingCriterionMap[$groupName][$criterionName] = $existingCriterion;
            $existingGroupMap[$groupName]                     = $existingCriterion->getGroup();
        }

        // Build new Rubric for destination box
        foreach ($srcDescriptors as $srcDescriptor) {

            // Copy source group, or reuse existing
            $srcGroupId = $srcDescriptor->getCriterion()->getGroup()->getId();
            if (!$srcGroups->getOffset($srcGroupId)) {
                $srcGroup = $srcDescriptor->getCriterion()->getGroup();
                $srcGroups->setOffset($srcGroupId, $srcGroup);
            }


            // Copy source Criterion or re-use existing Criterion
            $srcCriterionId = $srcDescriptor->getCriterion()->getId();
            if (!$srcCriteria->getOffset($srcCriterionId)) {
                $srcCriterion = $srcDescriptor->getCriterion();
                $srcCriteria->setOffset($srcCriterionId, $srcCriterion);
            }


            // Does this Criterion already exist on destination folder ?
            $duplicateGroup     = false;
            $duplicateCriterion = false;
            $groupName          = $this->normaliseForComparison($srcGroups->getOffset($srcGroupId)->getName());
            $criterionName      = $this->normaliseForComparison($srcCriteria->getOffset($srcCriterionId)->getName());
            if (array_key_exists($groupName, $existingGroupMap)) {
                $duplicateGroup = true;
                if (array_key_exists($criterionName, $existingCriterionMap[$groupName])) {
                    $duplicateCriterion = true;
                }
            }

            // Did we find an existing group with the same name?
            if ($duplicateGroup) {
                $dstGroup = $existingGroupMap[$groupName];
            } else {
                $dstGroup = clone $srcDescriptor->getCriterion()->getGroup();
                $dstGroup->setId(null);
                $dstGroup->setParentId($dstBox->getCategory()->getId());
                $existingGroupMap[$groupName] = $dstGroup;
            }
            $dstGroups->setOffset($srcGroupId, $dstGroup);


            // Did we find an existing Criterion with the same name?
            if ($duplicateCriterion) {
                $dstCriterion = $existingCriterionMap[$groupName][$criterionName];
            } else {
                $dstCriterion = clone $srcDescriptor->getCriterion();
                $dstCriterion->setId(null);
                $dstCriterion->setGroup($dstGroup);
                $existingCriterionMap[$groupName][$criterionName] = $dstCriterion;
            }
            $dstCriteria->setOffset($srcCriterionId, $dstCriterion);


            // Descriptor
            $dstDescriptor = clone $srcDescriptor;
            $dstDescriptor->setId(null);
            $dstDescriptors->setOffset($srcDescriptor->getId(), $dstDescriptor);


            // Link Descriptor + Level
            /** @var Level $dstLevel */
            $dstLevel = $dstLevels->getOffset($srcDescriptor->getLevel()->getId());
            $dstDescriptor->setLevel($dstLevel);
            $dstLevel->getDescriptors()->setOffset($srcDescriptor->getId(), $dstDescriptor);


            // Link Descriptor + Criterion
            $dstDescriptor->setCriterion($dstCriteria->getOffset($srcCriterionId));
        }

        // Get Source Usages
        /** @var GroupUsage[]|GenericCollection $srcGroupUsages */
        /** @var CriterionUsage[]|GenericCollection $srcCriterionUsages */
        $srcGroupUsages = $groupUsageMapper->findByColumnList([
            'rubric_group_id'   => $srcGroups->getPropertyValues('id'),
            'submission_box_id' => $sourceBox->getId(),
        ]);
        $srcCriterionUsages = $criterionUsageMapper->findByColumnList([
            'criterion_id'      => $srcCriteria->getPropertyValues('id'),
            'submission_box_id' => $sourceBox->getId(),
        ]);

        // Copy Group Usages
        foreach ($srcGroupUsages as $srcGroupUsage) {
            $dstGroupUsage = clone $srcGroupUsage;

            /** @var Group $dstGroup */
            $dstGroup = $dstGroups->getOffset($srcGroupUsage->getGroup()->getId());

            $dstGroupUsage->setGroup($dstGroup);
            $dstGroupUsage->setSubmissionBox($dstBox);
            $dstGroupUsages->add($dstGroupUsage);
        }

        // Copy Criterion Usages
        foreach ($srcCriterionUsages as $srcCriterionUsage) {
            $dstCriterionUsage = clone $srcCriterionUsage;

            /** @var Criterion $dstCriterion */
            $dstCriterion = $dstCriteria->getOffset($srcCriterionUsage->getCriterion()->getId());

            $dstCriterionUsage->setCriterion($dstCriterion);
            $dstCriterionUsage->setSubmissionBox($dstBox);
            $dstCriterionUsages->add($dstCriterionUsage);
        }

        // Save (order is important to ensure IDs are assigned to parents b4 saving children)
        $levelMapper->save($dstLevels);
        $groupMapper->save($dstGroups);
        $criterionMapper->save($dstCriteria);
        $descriptorMapper->save($dstDescriptors);
        $groupUsageMapper->save($dstGroupUsages);
        $criterionUsageMapper->save($dstCriterionUsages);

        return true;
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
     * @internal  string       $data [sb.id][rc.id]['grouping'] criterion.grouping
     * @internal  string       $data [sb.id][rc.id]['name'] criterion.name
     * @internal  string       $data [sb.id][rc.id]['max_mark'] max mark
     * @internal  array        $data [sb.id][rc.id]['marks']
     * @internal  float        $data [sb.id][rc.id]['marks'][student.id]
     * @throws UnderflowException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getStudentMarksByCriteriaInFolder(array $folderIds, TermModel $term)
    {
        if (!count($folderIds)) {
            return [];
        }

        /* 1. Get latest student published marks per criterion and dueWork
         * 2. Find the max descriptor value per criterion and dueWork.
         * 3. Convert each student mark into a normalised value
         *
         * We could perform steps 1+2 in a single query using a correlated subquery, but
         * that would mean the subquery is executed for every row fetched by the outer query.
         * In our case it is much faster to just fetch the max descriptors in a subsequent light-weight query.
         * (eg, consider there is no point executing the subquery for different students)
         * @see http://www.sqlservice.se/sql-server-performance-death-by-correlated-subqueries/
         */
        $pb = new Builder($this->database, '');
        $pb->in('rg.parent_id', $folderIds)
           ->equals('rg.parent_type', Group::PARENT_TYPE_CATEGORY)
           ->greaterThanOrEqualTo('a.due_date', $term->getStart()->getDatabase())
           ->equals('sr.review_route', SubmissionBox\ReturnedWork::REVIEW_ROUTE_INSTRUCTOR);

        $finish = $term->getActualFinish();
        if (null !== $finish && !$finish->isNull()) {
            $pb->lessThan('a.due_date', $finish->getDatabase());
        }

        $whereClauseInner = implode(' AND ', $pb->getWhere());
        $whereClauseOuter = implode(' AND ', $pb->getWhere());

        // 1. Get latest published student marks
        $stmt = $this->database->prepare("
            SELECT sr.owner, sb.id AS 'sb.id', rc.id AS 'rc.id', rm.value AS 'mark'
            FROM rubric_descriptor_mark rm
            JOIN rubric_descriptor      rd ON rd.id = rm.descriptor_id AND rd.deleted_at IS NULL
            JOIN rubric_criterion       rc ON rc.id = rd.criterion_id AND rc.deleted_at IS NULL
            JOIN rubric_group           rg ON rg.id = rc.rubric_group_id AND rg.deleted_at IS NULL
            JOIN rubric_group_mark     rgm ON rg.id = rgm.rubric_group_id
            JOIN submission_return      sr ON sr.id = rgm.return_id
            JOIN submission_box         sb ON sb.id = sr.box_id
            JOIN assessment              a ON  a.id = sb.assessment_id
            JOIN (
                -- ^Changed from LEFT JOIN to JOIN to allow filter out deleted feedbacks 
                -- Get the latest published marks
                SELECT
                    MAX(sr.published_at) AS 'max_published_at',
                    sr.id AS r_id,
                    sr.owner AS 'student_id',
                    sr.box_id AS 'sb_id'
                FROM rubric_descriptor_mark rm
                JOIN rubric_descriptor      rd ON rd.id = rm.descriptor_id AND rd.deleted_at IS NULL
                JOIN rubric_criterion       rc ON rc.id = rd.criterion_id AND rc.deleted_at IS NULL
                JOIN rubric_group           rg ON rg.id = rc.rubric_group_id AND rg.deleted_at IS NULL
                JOIN rubric_group_mark     rgm ON rg.id = rgm.rubric_group_id
                JOIN submission_return      sr ON sr.id = rgm.return_id
                JOIN submission_box         sb ON sb.id = sr.box_id
                JOIN assessment              a ON  a.id = sb.assessment_id
                WHERE {$whereClauseInner}
                AND rm.valid
                AND sr.published_at IS NOT NULL
                GROUP BY sr.owner, sr.box_id, sr.id
            ) t ON t.r_id = rm.return_id
            WHERE {$whereClauseOuter}
            AND rm.valid
            AND sr.published_at IS NOT NULL;
        ");
        $stmt->execute($pb->getParams());
        $studentMarks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Get max mark per criterion and dueWork
        $pb = new Builder($this->database, '');
        $pb->in('rg.parent_id', $folderIds)
           ->equals('rg.parent_type', Group::PARENT_TYPE_CATEGORY)
           ->greaterThan('rd.value', 0.0);
        $whereClause = implode(' AND ', $pb->getWhere());
        $stmt        = $this->database->prepare("
            SELECT rl.box_id AS 'sb.id', rc.id AS 'rc.id', rg.name AS grouping, rc.name, MAX(rd.max_value) AS 'max_mark'
            FROM rubric_descriptor rd
            JOIN rubric_criterion rc ON rc.id = rd.criterion_id AND rc.deleted_at IS NULL
            JOIN rubric_group     rg ON rg.id = rc.rubric_group_id AND rg.deleted_at IS NULL
            JOIN rubric_level     rl ON rl.id = rd.level_id AND rl.deleted_at IS NULL
            WHERE {$whereClause}
            GROUP BY rl.box_id, rc.id
            ORDER BY rl.box_id, rc.id
        ");
        $stmt->execute($pb->getParams());
        // 3. Convert each student mark into a normalised value
        $results = [];

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $boxId       = $row['sb.id'];
            $criterionId = $row['rc.id'];

            if (!is_array($results[$boxId])) {
                $results[$boxId] = [];
            }
            $results[$boxId][$criterionId] = [
                'grouping' => $row['grouping'],
                'name'     => $row['name'],
                'maxMark'  => $row['max_mark'],
                'marks'    => [],
            ];
        }

        foreach ($studentMarks as $row) {
            $mark = $row['mark'];
            if (null === $mark) {
                // If student does not have a numeric mark, cannot calculate normalised mark
                continue;
            }
            $boxId       = $row['sb.id'];
            $criterionId = $row['rc.id'];
            $maxMark     = $results[$boxId][$criterionId]['maxMark'] ?? null;
            if (null === $maxMark) {
                // This criterion has a max mark of 0.0 and therefore was not fetched into results array. Cannot calculate a normalised mark
                continue;
            }

            $results[$boxId][$criterionId]['marks'][$row['owner']] = 100.0 * $mark / $maxMark;
        }
        return $results;
    }


    /**
     * Get all criteria available on the specified parent
     *
     * TODO this is copied wholesale from now-deleted `Duework_Rubric_Handler`; consider modernizing
     *
     * @param int[]  $parentIds
     * @param string $parentType
     *
     * @return array
     * @var string   $criteria [rc.id]['grouping']
     * @var string   $criteria [rc.id]['name']
     */
    public function getAvailableCriteria(array $parentIds, $parentType = Group::PARENT_TYPE_CATEGORY)
    {
        if (!count($parentIds)) {
            return [];
        }

        $pb = new Builder($this->database, '');
        $pb->in('rg.parent_id', $parentIds)
           ->equals('rg.parent_type', $parentType);

        $whereClause = implode(' AND ', $pb->getWhere());

        $stmt = $this->database->prepare("
            SELECT rg.name AS grouping, rc.id, rc.name
            FROM rubric_criterion rc
            JOIN rubric_group     rg ON rg.id = rc.rubric_group_id AND rg.deleted_at IS NULL
            WHERE {$whereClause}
            AND rc.deleted_at IS NULL
            AND rg.deleted_at IS NULL
            ORDER BY rg.name ASC, rc.name ASC
        ");
        $stmt->execute($pb->getParams());

        $criteria = [];
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $criteria[$row['id']] = [
                'grouping' => $row['grouping'],
                'name'     => $row['name'],
            ];
        }
        return $criteria;
    }


    /* Getters/Setters
     */

    /**
     * @return Database
     */
    public function getDatabase()
    {
        return $this->database;
    }
}
