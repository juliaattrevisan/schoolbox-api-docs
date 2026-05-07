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
     * @var RosettaStone|mixed
     */
    private RosettaStone $l10n;


    /**
     * Decorator constructor.
     *
     * @param int $acceptOnline
     * @param int $markType
     * @param int $instructorMarkType
     * @param int|null $gradingScheme
     * @param int|null $instructorGradingScheme
     * @param float|null $maxMark
     * @param float|null $instructorMaxMark
     * @param array|null $rubricData
     * @param array $attachedFiles
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
        int $acceptOnline,
        int $markType,
        int $instructorMarkType,
        ?int $gradingScheme,
        ?int $instructorGradingScheme,
        ?float $maxMark,
        ?float $instructorMaxMark,
        ?array $rubricData,
        array $attachedFiles,
        ?string $selfReviewable,
        bool $similarityCheckEnabled,
        bool $enableWorksheet,
        ?string $worksheetTemplate,
        bool $enableOffline,
        bool $enableText,
        bool $enableFiles,
        bool $enablePolicy,
        private ?DueWork $original
    ) {
        $this->setAcceptOnline($acceptOnline);
        $this->setMarkType($markType);
        $this->setInstructorMarkType($instructorMarkType);
        $this->setGradingScheme($gradingScheme);
        $this->setInstructorGradingScheme($instructorGradingScheme);
        $this->setMaxMark($maxMark);
        $this->setInstructorMaxMark($instructorMaxMark);
        $this->setRubricData($rubricData);
        $this->setAttachedFiles($attachedFiles);
        $this->setSelfReviewable($selfReviewable);
        $this->setSimilarityCheckEnabled($similarityCheckEnabled);
        $this->setEnableWorksheet($enableWorksheet);
        $this->setWorksheetTemplate($worksheetTemplate);
        $this->setEnableOffline($enableOffline);
        $this->setEnableText($enableText);
        $this->setEnableFiles($enableFiles);
        $this->setEnablePolicy($enablePolicy);

        /* TODO Inject these, one day
         */
        $this->markTypeMapper = MarkTypeMapper::getInstance();
        $this->gradeGroupMapper = GradeLevel\Group\Mapper::getInstance();
        $this->unitSpecificationMapper = UnitSpecificationMapper::getInstance();
        $this->rubricMapper = RubricDataSource::getInstance();
        $this->rubricInstanceMapper = \Schoolbox\Assessment\Rubric\Instance\Mapper::getInstance();

        // (Forgive me too for perpetuating this)
        /** @var RosettaStone $l10n */
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

        /* If there are any rubric grids, we need to process each one for this assessment.
         * We also assign the assessment's maximum available marks as the summation of all the grids' maxima.
         *
         * Note that you can now spawn multiple grids from the frontend; the all end up here via one mega payload.
         * This mega payload contains a mix of previously-existing grids and totally new ones.
         */
        foreach ($this->getRubricData() as $rubricId => $payload) {
            /* This rubric instance already existed in the assessment; it already has a meaningful identifier.
             * This builds on the frontend assumption that totally new rubric grids have a negative int identifier,
             * because you can spawn multiple new grids in a payload here...such identifiers just become more and more
             * negative, making them signed ints in practice but when they're persisted, they'll be unsigned.
             */
            if ($rubricId > 0) {
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
                ->setId($rubricId)
                ->setAssessmentModel((new \Schoolbox\Learning\Assessment\DataModel\Model)->setId($assessment->getId()));

            /* "Welcome to the party, pal." --John McClane
             */
            $assessment->getRubricInstances()->add($newRubricInstance);
        }

        if ($assessment->getRubricInstances()->count()) {
            /* Populate rubric_instance records with settings from the rubricData payload
             */
            $rubricMapper = $this->rubricMapper;
            $gridFactory  = new GridFactory();

            /* An assessment's maxMark is the largest possibility, defined by the aggregation of all rubric
             * grids. I'm sure there's a case to be made where some grids aren't supposed to contribute to an
             * assessment's maximum marks but we'll just not entertain that theory for now
             */
            $maxMark = 0;

            foreach ($assessment->getRubricInstances() as $rubricInstance) {
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

        /* TODO reimplement somehow
         */
//        if ($this->getUseRubric() && $this->original) {
//            // relocating
//            $rubricMapper = RubricDataSource::getInstance();
//            $gridFactory  = new GridFactory();
//
//            $rowSetBeforeMove = $rubricMapper->retrieveRowSet($this->original->getBaseContext(), true);
//            $existingGrid     = new Grid($this->original, $rowSetBeforeMove);
//            $existingGrid     = $gridFactory->buildFromPostData($existingGrid, $this->rubricData, false);
//
//            $importer = new GenericImporter(
//                $existingGrid,
//                $assessment->getUseMarkRange(),
//                $assessment->getCapabilityMarkType()->getId(),
//                $assessment->getCapabilityGradeGroup()->getId()
//            );
//
//            // Now new assessment should have new grid
//            $rubricMapper->importGrid($assessment, $importer);
//            $markMax = $assessment->getRubricGrid()->calculateMaxValueFromCapabilities();
//            $assessment->setMaxMark($markMax);
//            $assessment->setInstructorMaxMark($markMax);
//        } elseif ($this->getUseRubric()) {
//            Already reimplemented above
//        } else {
//            Already reimplemented above
//        }

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
