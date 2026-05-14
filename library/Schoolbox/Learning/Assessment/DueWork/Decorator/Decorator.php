<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Decorator;

use DomainException;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RangeException;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\Grid\Factory as GridFactory;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\Grid\Importer\GenericImporter;
use Schoolbox\Assessment\Rubric\RowSet\ValidRowSet;
use Schoolbox\Collection;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\AttachedFilesTrait;
use Schoolbox\Learning\Assessment\DataModel\RubricTrait;
use Schoolbox\Learning\Assessment\DecoratorInterface;
use Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use UnexpectedValueException;

/**
 * Class Decorator
 *
 * Decorate an IAssessable instance with Due Work-specific fields
 *
 * @package Schoolbox\Learning\Assessment\DueWork\Decorator
 */
class Decorator implements DecoratorInterface
{
    use DueWorkTrait;
    use AttachedFilesTrait;
    use RubricTrait;


    /**
     * @var MarkTypeMapper
     */
    private MarkTypeMapper $markTypeMapper;


    /**
     * @var GradeLevel\Group\Mapper
     */
    private GradeLevel\Group\Mapper $gradeGroupMapper;


    /**
     * @var UnitSpecificationMapper
     */
    private UnitSpecificationMapper $unitSpecificationMapper;


    /**
     * @var RubricDataSource
     */
    private RubricDataSource $rubricMapper;


    /**
     * @var \Schoolbox\Assessment\Rubric\Instance\Mapper
     */
    private \Schoolbox\Assessment\Rubric\Instance\Mapper $rubricInstanceMapper;


    /**
     * @var RosettaStone
     */
    private RosettaStone $l10n;


    /**
     * Decorator constructor.
     *
     * Note that $attachedFiles and $rubricData doesn't have private next to it.
     * Doing so results in this, for some reason:
     *    Schoolbox\Learning\Assessment\DueWork\Decorator\Decorator and Schoolbox\Learning\Assessment\AttachedFilesTrait define the same property ($attachedFiles) in the composition of Schoolbox\Learning\Assessment\DueWork\Decorator\Decorator. However, the definition differs and is considered incompatible. Class was composed in <b>/usr/share/schoolbox/library/Schoolbox/Learning/Assessment/DueWork/Decorator/Decorator.php</b>
     *
     * There's something peculiar about our usage of $attachedFiles that php traits finds objectionable.
     * Not sure what that is, was taking too long to figure it out.
     *
     * @param int $acceptOnline
     * @param int $markType
     * @param int $instructorMarkType
     * @param int|null $gradingScheme
     * @param int|null $instructorGradingScheme
     * @param float|null $maxMark
     * @param float|null $instructorMaxMark
     * @param array|null $rubricData
     * @param array|null $attachedFiles
     * @param string|null $selfReviewable
     * @param bool $similarityCheckEnabled
     * @param bool $enableWorksheet
     * @param string|null $worksheetTemplate
     * @param bool $enableOffline
     * @param bool $enableText
     * @param bool $enableFiles
     * @param bool $enablePolicy
     * @param DueWork|null $original
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(
        private int $acceptOnline,
        private int $markType,
        private int $instructorMarkType,
        private ?int $gradingScheme,
        private ?int $instructorGradingScheme,
        private ?float $maxMark,
        private ?float $instructorMaxMark,
        ?array $rubricData,
        ?array $attachedFiles,
        private ?string $selfReviewable,
        private bool $similarityCheckEnabled,
        bool $enableWorksheet,
        ?string $worksheetTemplate,
        bool $enableOffline,
        bool $enableText,
        bool $enableFiles,
        bool $enablePolicy,
        private ?DueWork $original
    ) {
        /* For unknown reasons, php finds our usage of traits objectionable: I have to go back to doing it like this.
         * (Our constructor definitions are in conflict with traits' definitions)
         */
        $this->setEnableWorksheet($enableWorksheet);
        $this->setWorksheetTemplate($worksheetTemplate);
        $this->setEnableOffline($enableOffline);
        $this->setEnableText($enableText);
        $this->setEnableFiles($enableFiles);
        $this->setEnablePolicy($enablePolicy);
        $this->setRubricData($rubricData);
        $this->setAttachedFiles($attachedFiles);

        /* TODO Inject these, one day
         */
        $this->markTypeMapper = MarkTypeMapper::getInstance();
        $this->gradeGroupMapper = GradeLevel\Group\Mapper::getInstance();
        $this->unitSpecificationMapper = UnitSpecificationMapper::getInstance();
        $this->rubricMapper = RubricDataSource::getInstance();
        $this->rubricInstanceMapper = \Schoolbox\Assessment\Rubric\Instance\Mapper::getInstance();

        // (Forgive me too for perpetuating this)
        $this->l10n = \Schoolbox\Mapper::getDi()->get('l10n');
    }


    /**
     * @param DueWork $assessment
     * @throws Exception
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function decorate($assessment)
    {
        if ($this->getAcceptOnline() == DueWork::ACCEPT_ONLINE_NONE) {
            $assessment->setAcceptsNone();
        } elseif ($this->getAcceptOnline() == DueWork::ACCEPT_ONLINE_SINGLE) {
            $assessment->setAcceptsSingle();
        } elseif ($this->getAcceptOnline() == DueWork::ACCEPT_ONLINE_MULTI) {
            $assessment->setAcceptsMultiple();
        }

        /* Set matching data types
         *
         * Ideally these would be hydrated like everything else, but for some reason,
         * I end up drowning in the Swamp if I try
         */
        $markType = $this->markTypeMapper->createModel()->setId($this->getMarkType());
        $instructorMarkType = $this->markTypeMapper->createModel()->setId($this->getInstructorMarkType());
        $gradingGroup = $this->gradeGroupMapper->createModel()->setId($this->getGradingScheme());
        $instructorGradingGroup = $this->gradeGroupMapper->createModel()->setId($this->getInstructorGradingScheme());

        $assessment->setMarkType($markType)
            ->setInstructorMarkType($instructorMarkType)
            ->setGradeGroup($gradingGroup)
            ->setInstructorGradeGroup($instructorGradingGroup);

        //        $capabilityMarkType = MarkTypeMapper::getInstance()->createModel()->setId($this->getCapabilityMarkType());
        //        /** @var SubmissionBox\GradeLevel\Group\Model $capabilityGradeGroup */
        //        $capabilityGradeGroup = Mapper::getInstance()->createModel()->setId($this->getCapabilityGradeGroup());
        //
        //        $assessment->setCapabilityMarkType($capabilityMarkType);
        //        $assessment->setCapabilityGradeGroup($capabilityGradeGroup);

        if ($this->getSelfReviewable() !== null) {
            // Something has come through from post, set to that
            $assessment->setSelfReviewability($this->getSelfReviewable());
        } elseif ($assessment->getSelfReviewability() === null) {
            // $assessment doesn't have an existing setting - set to default
            $assessment->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_NONE);
        } // Otherwise let the existing setting persist

        /* TODO Move this whole rubric processing chunk into a separate class (Rubric Decorator)
         *  Other learning activities will end up following the same idea later so we need to make this an
         *  easily-propagated idea...essentially, a Rubric Decorator. It takes an $assessment and $this->getRubricData()
         *  to populate the $assessment with rubric instances, grids, and the max marks stuff.
         */

        /* These are the rubric instance identifiers that were posted to us from the ui
         */
        $keepRubricInstanceIds = array_keys($this->getRubricData());

        /* If there are any rubric grids, we need to process each one for this assessment.
         * We also assign the assessment's maximum available marks as the summation of all the grids' maxima.
         *
         * Note that you can now spawn multiple grids from the frontend; the all end up here via one mega payload.
         * This mega payload contains a mix of previously-existing grids and totally new ones.
         *
         * Note that this null coalesce is necessary because Assessment Factory might have set this null when creating a
         * new learning activity that never used rubrics. This set will be null in such a circumstance.
         */
        foreach ($this->getRubricData() ?? [] as $rubricInstanceId => $payload) {
            /* This rubric instance already existed in the assessment; it already has a meaningful identifier.
             * This builds on the frontend assumption that totally new rubric grids have a negative int identifier,
             * because you can spawn multiple new grids in a payload here...such identifiers just become more and more
             * negative, making them signed ints in practice but when they're persisted, they'll be unsigned.
             *
             * TODO There's this bug where the frontend payload sends 'null' as a rubric identifier: rubric[null] = ...
             *  This equates to $rubricId > 0 bool(true), causing a new grid to be skipped here. It means you also share
             *  an identifier when you spawn multiple new grids via csv; they all have the same 'null' index. This
             *  constraints you to csv importing one grid at a time, for now: idk where this occurs in the frontend.
             */
            if (is_numeric($rubricInstanceId) && $rubricInstanceId > 0) {
                continue;
            }

            /* This is a new grid: we need to add it to the stack. It won't be there already; it's never been persisted.
             * persistGrid() will save the rubric instance with a more meaningful identifier later, though this might
             * sound like a bit of a gotcha if you didn't know how this area was made.
             *
             * Its settings are assembled via buildFromPostData() which is essentially "hydrate this grid from an array"
             * but this is a real weird hydration that I have to do here.
             */
            $newRubricInstance = $this->rubricInstanceMapper->createModel()
                ->setId($rubricInstanceId)
                ->setTitle($payload['title'])
                ->setWeight($payload['weight'])
                ->setAssessmentModel((new \Schoolbox\Learning\Assessment\DataModel\Model())->setId($assessment->getId()));

            /* "Welcome to the party, pal." --John McClane
             */
            $assessment->getRubricInstances()->add($newRubricInstance);
        }

        /* If rubric_instances exist, populate them with settings from the rubricData payload: this updates the settings
         * for each grid that we intend to keep.
         */
        if ($assessment->getRubricInstances()->count()) {
            $rubricMapper = $this->rubricMapper;
            $gridFactory  = new GridFactory();

            /* An assessment's maxMark is the largest possibility, defined by the aggregation of all rubric
             * grids. I'm sure there's a case to be made where some grids aren't supposed to contribute to an
             * assessment's maximum marks but we'll just not entertain that theory for now
             */
            $maxMark = 0;

            foreach ($assessment->getRubricInstances() as $rubricInstance) {
                /* If this is null, then $assessment knew about a grid, but it's no longer in the getRubricData() post
                 * payload: it was removed via the ui.
                 */
                if (!isset($this->getRubricData()[$rubricInstance->getId()])) {
                    continue;
                }

                $existingGrid = new Grid(
                    $assessment,
                    $rubricInstance,
                    $rubricMapper->retrieveRowSet($assessment->getBaseContext(), true)
                );

                $newGrid = $gridFactory->buildFromPostData($existingGrid, $this->getRubricData()[$rubricInstance->getId()]);

                // TODO this should be a part of buildFromPostData, but I don't feel like injecting all its dependencies
                $spec = new ValidRowSet(
                    $this->l10n,
                    $this->unitSpecificationMapper,
                    $rubricMapper,
                    $existingGrid->getRowSet(),
                    false,
                    $newGrid->getAssessment()
                );

                // `isSatisfiedBy` needs to raise an exception instead of just
                // returning false.
                // If it does just return false: that's a logic exception, fix it
                if (!$spec->isSatisfiedBy($newGrid->getRowSet())) {
                    throw new InvalidArgumentException("Save of row set failed for unknown reason");
                }

                /* LOL I needed something similar to this
                 * $assessment->setRubricGrid($newGrid);
                 *
                 * This is a bit of a gotcha, unfortunately
                 * This might be a bit convoluted as you have an existing $rubricInstance that contains a revised version
                 * of its grid, $newGrid. Out of context this might be difficult to comprehend, but I don't have any real
                 * sensible storage locations other than this.
                 *
                 * Once the $assessment has been manufactured, another place saves it, and needs the revised $newGrid.
                 */
                $rubricInstance->setGrid($newGrid);

                /* Aggregate maximum mark of this grid
                 */
                $maxMark += $newGrid->calculateMaxValueFromCapabilities();
            }

            /* Remove any rubric instances that the user intended to be deleted
             */
            $revisedRubricInstances = $assessment->getRubricInstances()->filter(static function (\Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance) use ($keepRubricInstanceIds) {
                return in_array($rubricInstance->getId(), $keepRubricInstanceIds);
            });

            $assessment->setRubricInstances($revisedRubricInstances);

            /* The max mark is the aggregation of each grid's maximum
             */
            $assessment
                ->setMaxMark($maxMark)
                ->setInstructorMaxMark($maxMark);
        } else {
            /* The max mark is always whatever got sent in the form; it was entered by a human
             */
            $assessment
                ->setMaxMark($this->getMaxMark())
                ->setInstructorMaxMark($this->getInstructorMaxMark());
        }

        /* The existence of $this->>original specifically denotes the workflow where you're Changing Location of an
         * assessment. This is such a cryptic usage, but it stems from this implementation in ListController, where
         * the only assignation of $originalAssessment occurs:

            // Clear assessment if the context is changed
            $relocatedContext   = $assessment ? $this->getRelocatedContext($assessment, $postData['locationId'] ?? '') : '';
            $originalAssessment = null;

            if ($relocatedContext) {
                $postData['context'] = $relocatedContext;
                $originalAssessment  = $assessment;
                $assessment          = null;
            }
         *
         * At this point $assessment is nulled and forced to respawn entirely from the $post payload and the
         * corresponding decorator's invocation. You don't have access to the usual $assessment->getRubricInstances()
         * collection anymore: those are in $original. Also at this point in this particular workflow, $assessment
         * got deleted by some other means, expecting (assuming) success from recreation at the destination location.
         *
         * Wild.
         */
        if ($this->original) {
            $rubricMapper = RubricDataSource::getInstance();
            $gridFactory  = new GridFactory();

            $rowSetBeforeMove = $rubricMapper->retrieveRowSet($this->original->getBaseContext(), true);

            /* An assessment's maxMark is the largest possibility, defined by the aggregation of all rubric
             * grids. I'm sure there's a case to be made where some grids aren't supposed to contribute to an
             * assessment's maximum marks but we'll just not entertain that theory for now
             */
            $maxMark = 0;

            foreach ($this->original->getRubricInstances() as $originalRubricInstance) {
                /* Make a copy of what it originally did, but unlink its identifier so it spawns anew.
                 */
                /** @var \Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance */
                $rubricInstance = clone $originalRubricInstance;
                $rubricInstance->setId(null);

                /* Craft a grid that uses the settings of $originalRubricInstance, especially when we interrogate the
                 * $post payload via $this->getRubricData()[$originalRubricInstance->getId()] because we just cloned
                 * it into $rubricInstance which was used to manufacture this grid. We need to access the $post payload
                 * to find out what the settings *were*.
                 */
                $existingGrid = new Grid($this->original, $rubricInstance, $rowSetBeforeMove);
                $existingGrid = $gridFactory->buildFromPostData($existingGrid, $this->getRubricData()[$originalRubricInstance->getId()], false);

                $importer = new GenericImporter(
                    $existingGrid,
                    $rubricInstance->getMarkedUsingRange(),
                    $rubricInstance->getCapabilityMarkType()->getId(),
                    $rubricInstance->getCapabilityGradeGroup()->getId()
                );

                /* Note that $existingGrid is actually a *re-creation* of a grid that exists, using the $post data in
                 * buildFromPostData(). If someone changed the title at the same time as relocating an assessment we
                 * actually need *that* revised title: we can't get it from $originalRubricInstance (that's the old
                 * title...) but instead, we need to get it from the re-created rubric instance in $existingGrid.
                 *
                 * These names...!
                 */
                $newGrid = $rubricMapper->importGrid($assessment, $importer, $existingGrid->getRubricInstance()->getTitle());

                /* Each rubric instance knows the latest state of a grid, which also knew about its defining rubric
                 * instance: that was a cyclic dependency that emerged.
                 */
                $newGrid->getRubricInstance()->setGrid($newGrid);

                /* But, it has the same setup as the existing grid so I suppose we could accumulate the total max marks
                 * using that instead of the new grid. This is probably more intuitive though
                 */
                $maxMark += $newGrid->calculateMaxValueFromCapabilities();
            }

            /* The max mark is the aggregation of each grid's maximum
             */
            $assessment
                ->setMaxMark($maxMark)
                ->setInstructorMaxMark($maxMark);
        }

        // Only spawn the mark scheme after the max mark is finalized
        // (as it might have changed from what was submitted to the form)
        $markSchemeFactory = new MarkSchemeFactory();
        $assessment->setMarkScheme($markSchemeFactory->spawnMarkSchemeFor($assessment));
        $assessment->setInstructorMarkScheme($markSchemeFactory->spawnInstructorMarkSchemeFor($assessment));

        /* If there were attachments, accumulate them
         * Otherwise set attachedFiles to an empty collection
         */
        $newList = new Collection();

        if (count($this->getAttachedFiles())) {
            // Rebuild attached files list
            $factory     = new CollectorFactory();
            $attachments = $factory->build($this->getAttachedFiles())->getAll();

            foreach ($attachments as $file) {
                $newList->add(
                    (new DueWorkFile())
                        ->setStorage($file)
                        ->setBasename($file->getBasename())
                );
            }
        }

        $assessment->setAttachedFiles($newList);

        /* Similarity Check
         */
        $assessment->setSimilarityCheckEnabled($this->getSimilarityCheckEnabled());

        /* Are we in a course managed, and modifying something.
         * if there are no fields in the request (submission options are required) we should just re save what was in the original assessment
         * if there are fields being sent, irrespective if we are updating or creating, we should save those new fields.
         */
        if (
            $this->original && !(
                $this->getEnableWorksheet() ||
                $this->getEnableOffline() ||
                $this->getEnableText() ||
                $this->getEnableFiles()
            )
        ) {
            // we aren't posting the data when updating
            $assessment->setEnableWorksheet($this->original->getEnableWorksheet());
            $assessment->setWorksheetTemplate($this->original->getWorksheetTemplate());
            $assessment->setEnableOffline($this->original->getEnableOffline());
            $assessment->setEnableText($this->original->getEnableText());
            $assessment->setEnableFiles($this->original->getEnableFiles());
            $assessment->setEnablePolicy($this->original->getEnablePolicy());
        } else {
            $assessment->setEnableWorksheet($this->getEnableWorksheet());
            if ($this->getWorksheetTemplate()) {
                // Rebuild worksheet files list
                $factory     = new CollectorFactory();
                $attachments = $factory->build([$this->getWorksheetTemplate()])->getAll();
                $assessment->setWorksheetTemplate($attachments[0]);
            } else {
                $assessment->setWorksheetTemplate(null);
            }
            $assessment->setEnableOffline($this->getEnableOffline());
            $assessment->setEnableText($this->getEnableText());
            $assessment->setEnableFiles($this->getEnableFiles());
            $assessment->setEnablePolicy($this->getEnablePolicy());
        }
    }
}
