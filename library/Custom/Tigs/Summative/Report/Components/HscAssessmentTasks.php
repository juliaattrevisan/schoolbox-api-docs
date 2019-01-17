<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report\Components;

use Custom\Tigs\Summative\Report\StudentAttributes;
use Custom\Tigs\Summative\Report\BaseComponent;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use UnexpectedValueException;

class HscAssessmentTasks extends BaseComponent
{

    /** @var CourseSpec\Model */
    private $courseSpec;

    /** @var User */
    private $student;

    /** @var Visibility */
    private $visibility;

    /** @var User */
    private $viewer;

    /** @var int */
    private $workTypeId;

    /** @var Folder\Model */
    private $folder;


    /**
     * Constructor
     *
     * @param CourseSpec\Model  $courseSpec
     * @param User              $student
     * @param StudentAttributes $attributes
     * @param Visibility        $visibility
     * @param User              $viewer
     * @param int               $workTypeId
     * @param Folder\Model      $folder
     */
    public function __construct(
        CourseSpec\Model $courseSpec,
        User $student,
        StudentAttributes $attributes,
        Visibility $visibility,
        User $viewer,
        int $workTypeId,
        Folder\Model $folder
    ) {
        parent::__construct($attributes);

        $this->courseSpec      = $courseSpec;
        $this->student         = $student;
        $this->visibility      = $visibility;
        $this->viewer          = $viewer;
        $this->workTypeId      = $workTypeId;
        $this->folder          = $folder;
    }


    /**
     * Returns an array indexed by unitSpec.id
     *
     * @return array
     */
    public function toContext(): array
    {
        $data = [];
        foreach ($this->courseSpec->getUnits() as $unitSpec) {
            try {
                $unitSpecData = $this->buildUnitSpecData($unitSpec);
                if ($unitSpecData) {
                    $data[$unitSpec->getId()] = $unitSpecData;
                }
            } catch (\UnexpectedValueException $e) {
                $data[$unitSpec->getId()]['error'] = $e->getMessage();
            }
        }
        return $data;
    }


    /**
     * Builds data for a single UnitSpec
     *
     * @param UnitSpec\Model $unitSpec
     * @return array|null
     * @throws UnexpectedValueException If unable to generate data for this unit's component
     */
    private function buildUnitSpecData(UnitSpec\Model $unitSpec): ?array
    {
        $cohortAssessments = $this->findAssessmentsForFolderAndWorkType($unitSpec, null, $this->workTypeId);

        // Check they are all of expected assessment and mark type
        foreach ($cohortAssessments as $assessable) {
            if ($assessable instanceof DueWork\Model) {
                $markScheme = $assessable->getMarkScheme();
                if (!$markScheme instanceof Percentage) {
                    throw new UnexpectedValueException("HSC Task Marks must use the percentage mark type. Found '{$markScheme::getTitle()}'");
                }
            } else {
                throw new UnexpectedValueException("HSC Task Marks must be a Due Work, '{$assessable->getTitle()}' is an instance of {$assessable::getDefaultTypeName()}");
            }
        }

        // Filter anything not due within this report's window
        foreach ($cohortAssessments as $assessable) {
            if (!$this->isDueWithinReportWindow($assessable)) {
                $cohortAssessments->offsetUnset($assessable->getId());
            }
        }

        // Does the set include an assessment imported to this class?
        $classAssessment = null;
        foreach ($cohortAssessments as $assessment) {
            if ((int)$assessment->getBaseContext()->getFolder()->getId() === (int)$this->folder->getId()) {
                $classAssessment = $assessment;
                break;
            }
        }
        if (!$classAssessment || !$this->visibility->isVisibleToUserOrChildOfUser($classAssessment, $this->viewer, true, null, false)) {
            // Not imported to this class within report window, or not visible?
            return null;
        }

        // Ensure there is only 1 such assessment per UnitSpec imported to a class
        $cohortAssessmentsPerFolder = $cohortAssessments->groupByCallback(function (IAssessable $assessable) {
            return $assessable->getBaseContext()->getFolder()->getId();
        });
        foreach ($cohortAssessmentsPerFolder as $folderId => $assessments) {
            $count = $assessments->count();
            if ($count !== 1) {
                /** @var IAssessable $firstAssessment */
                $firstAssessment = $assessments->first();
                $folder          = $firstAssessment->getBaseContext()->getFolder();
                $folderLink      = \sprintf('<a href="/homepage/%d">%s</a>', $folder->getId(), $folder->getTitle());
                $unitName        = $unitSpec->getTitle();
                $msg             = "There must be only 1 'HSC Task Marks' assessments per class unit. Found {$count} in class '{$folderLink}' for unit '{$unitName}'";
                throw new UnexpectedValueException($msg);
            }
        }

        $feedback       = $this->findStudentFeedback($classAssessment);
        $normalisedMark = $feedback ? $feedback->getNormalisedMark() : null;
        $cohortMarks    = $this->deriveCohortMarks($cohortAssessments);
        $cohortRank     = $this->deriveCohortRank($normalisedMark, $cohortMarks);
        $countCohort    = \count($cohortMarks);
        $cohortAverage  = $this->deriveCohortAverage($cohortMarks);

        return [
            'studentMark'   => $feedback ? $feedback->getHumanReadableMark() : null,
            'cohortRank'    => null === $cohortRank ? null : "$cohortRank / $countCohort",
            'cohortAverage' => null === $cohortAverage ? null : "$cohortAverage %",
        ];
    }


    /**
     * Returns the student's authoritative feedback for the assessment.
     *
     * Returns NULL if feedback is not visible.
     *
     * @param IAssessable $assessable
     * @return IFeedback|null
     */
    private function findStudentFeedback(IAssessable $assessable): ?IFeedback
    {
        if (!$assessable->isFeedbackVisibleToUser($this->viewer)) {
            return null;
        }
        $participant = $assessable->getParticipantIfExtant($this->student);
        if (!$participant) {
            return null;
        }
        return $participant->getAuthoritativeFeedback(true);
    }


    /**
     * Returns all normalised marks for the cohort. Ignores feedback visibility
     *
     * @param GenericCollection|IAssessable[] $cohortAssessments
     * @return float[]
     */
    private function deriveCohortMarks(GenericCollection $cohortAssessments): array
    {
        $cohortMarks = [];
        foreach ($cohortAssessments as $assessable) {
            foreach ($assessable->getAllParticipants() as $participant) {
                $authFeedback = $participant->getAuthoritativeFeedback(true);
                if (!$authFeedback) {
                    continue;
                }
                $normalisedMark = $authFeedback->getNormalisedMark();
                if (null === $normalisedMark) {
                    continue;
                }
                $cohortMarks[] = $normalisedMark;
            }
        }
        return $cohortMarks;
    }


    /**
     * Returns the student's rank against the cohort
     *
     * @param float|null $studentMark
     * @param float[]    $cohortMarks
     * @return int|null
     */
    private function deriveCohortRank(?float $studentMark, array $cohortMarks): ?int
    {
        if (null === $studentMark) {
            return null;
        }
        \rsort($cohortMarks);

        $index = \array_search($studentMark, $cohortMarks, true);
        if (false === $index) {
            return null;
        }
        return $index + 1;
    }


    /**
     * Returns the average cohort mark, rounded to the specified precision
     *
     * @param float[] $cohortMarks
     * @param int     $precision
     * @return float|null
     */
    private function deriveCohortAverage(array $cohortMarks, int $precision = 2): ?float
    {
        if (!\count($cohortMarks)) {
            return null;
        }
        return \round(\array_sum($cohortMarks) / \count($cohortMarks), $precision);
    }
}
