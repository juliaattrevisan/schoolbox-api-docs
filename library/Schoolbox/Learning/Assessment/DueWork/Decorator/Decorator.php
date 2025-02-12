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
use RangeException;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\Grid\Factory as GridFactory;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\Grid\Importer\GenericImporter;
use Schoolbox\Assessment\Rubric\RowSet\ValidRowSet;
use Schoolbox\Collection;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\AttachedFilesTrait;
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

    /** @var DueWork|null $original */
    private $original;

    /**
     * Decorator constructor.
     *
     * @param int          $acceptOnline
     * @param int          $markType
     * @param int          $instructorMarkType
     * @param int|null     $gradingScheme
     * @param int|null     $instructorGradingScheme
     * @param bool         $useRubric
     * @param int          $capabilityMarkType
     * @param int|null     $capabilityGradeGroup
     * @param bool         $useMarkRange
     * @param float|null   $maxMark
     * @param float|null   $instructorMaxMark
     * @param array|null   $rubricData
     * @param array        $attachedFiles
     * @param string|null  $selfReviewable
     * @param bool         $similarityCheckEnabled
     * @param bool         $enableWorksheet
     * @param string|null  $worksheetTemplate
     * @param bool         $enableOffline
     * @param bool         $enableText
     * @param bool         $enableFiles
     * @param bool         $enablePolicy
     * @param DueWork|null $original
     */
    public function __construct(
        int $acceptOnline,
        int $markType,
        int $instructorMarkType,
        ?int $gradingScheme,
        ?int $instructorGradingScheme,
        bool $useRubric,
        int $capabilityMarkType,
        ?int $capabilityGradeGroup,
        bool $useMarkRange,
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
        ?DueWork $original
    ) {
        $this->setAcceptOnline($acceptOnline);
        $this->setMarkType($markType);
        $this->setInstructorMarkType($instructorMarkType);
        $this->setGradingScheme($gradingScheme);
        $this->setInstructorGradingScheme($instructorGradingScheme);
        $this->setUseRubric($useRubric);
        $this->setCapabilityMarkType($capabilityMarkType);
        $this->setCapabilityGradeGroup($capabilityGradeGroup);
        $this->setUseMarkRange($useMarkRange);
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
        $this->original = $original;
    }

    /**
     * @param DueWork $assessment
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws RangeException
     * @throws Exception
     * @throws UnexpectedValueException
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
        $markType = MarkTypeMapper::getInstance()->createModel()->setId($this->getMarkType());
        $instructorMarkType = MarkTypeMapper::getInstance()->createModel()->setId($this->getInstructorMarkType());
        $gradingGroup = Mapper::getInstance()->createModel()->setId($this->getGradingScheme());
        $instructorGradingGroup = Mapper::getInstance()->createModel()->setId($this->getInstructorGradingScheme());

        $capabilityMarkType = MarkTypeMapper::getInstance()->createModel()->setId($this->getCapabilityMarkType());
        /** @var SubmissionBox\GradeLevel\Group\Model $capabilityGradeGroup */
        $capabilityGradeGroup = Mapper::getInstance()->createModel()->setId($this->getCapabilityGradeGroup());

        $assessment->setMarkType($markType);
        $assessment->setInstructorMarkType($instructorMarkType);
        $assessment->setGradeGroup($gradingGroup);
        $assessment->setInstructorGradeGroup($instructorGradingGroup);
        $assessment->setUseRubric($this->getUseRubric());
        $assessment->setCapabilityMarkType($capabilityMarkType);
        $assessment->setCapabilityGradeGroup($capabilityGradeGroup);
        $assessment->setUseMarkRange($this->getUseMarkRange());

        if ($this->getSelfReviewable() !== null) {
            // Something has come through from post, set to that
            $assessment->setSelfReviewability($this->getSelfReviewable());
        } elseif ($assessment->getSelfReviewability() === null) {
            // $assessment doesn't have an existing setting - set to default
            $assessment->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_NONE);
        } // Otherwise let the existing setting persist

        if ($this->getUseRubric() && $this->original) {
            // relocating
            $rubricMapper = RubricDataSource::getInstance();
            $gridFactory  = new GridFactory();

            $rowSetBeforeMove = $rubricMapper->retrieveRowSet($this->original->getBaseContext(), true);
            $existingGrid     = new Grid($this->original, $rowSetBeforeMove);
            $existingGrid     = $gridFactory->buildFromPostData($existingGrid, $this->rubricData, false);

            $importer = new GenericImporter(
                $existingGrid,
                $assessment->getUseMarkRange(),
                $assessment->getCapabilityMarkType()->getId(),
                $assessment->getCapabilityGradeGroup()->getId()
            );

            // Now new assessment should have new grid
            $rubricMapper->importGrid($assessment, $importer);
            $markMax = $assessment->getRubricGrid()->calculateMaxValueFromCapabilities();
            $assessment->setMaxMark($markMax);
            $assessment->setInstructorMaxMark($markMax);
        } elseif ($this->getUseRubric()) {
            $rubricMapper = RubricDataSource::getInstance();
            $gridFactory  = new GridFactory();
            $existingGrid = new Grid(
                $assessment,
                $rubricMapper->retrieveRowSet($assessment->getBaseContext(), true)
            );
            $newGrid = $gridFactory->buildFromPostData($existingGrid, $this->getRubricData());

            // (Forgive me too for perpetuating this)
            /** @var RosettaStone $l10n */
            // TODO this should be a part of buildFromPostData, but I don't feel like injecting all its dependencies
            $l10n = \Schoolbox\Mapper::getDi()->get('l10n');
            $spec = new ValidRowSet(
                $l10n,
                UnitSpecificationMapper::getInstance(),
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

            $assessment->setRubricGrid($newGrid);

            // The max mark for a rubric-using assessment is always the same as
            // the max mark from its capabilities
            $markMax = $newGrid->calculateMaxValueFromCapabilities();
            $assessment->setMaxMark($markMax);
            $assessment->setInstructorMaxMark($markMax);
        } else {
            // The max mark is always whatever got sent in the form
            $assessment->setMaxMark($this->getMaxMark());
            $assessment->setInstructorMaxMark($this->getInstructorMaxMark());
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
