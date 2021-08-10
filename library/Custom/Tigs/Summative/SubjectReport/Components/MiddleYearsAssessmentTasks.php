<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport\Components;

use Custom\Tigs\Summative\StudentAttributes;
use Custom\Tigs\Summative\SubjectReport\BaseComponent;
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

use function sprintf;
use function array_key_exists;
use function count;

class MiddleYearsAssessmentTasks extends BaseComponent
{

    /** @var CourseSpec\Model */
    private $courseSpec;

    /** @var User */
    private $student;

    /** @var Visibility */
    private $visibility;

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
     * @param string|null       $roleTypeOverride
     * @throws \InvalidArgumentException
     */
    public function __construct(
        CourseSpec\Model $courseSpec,
        User $student,
        StudentAttributes $attributes,
        Visibility $visibility,
        User $viewer,
        int $workTypeId,
        Folder\Model $folder,
        ?string $roleTypeOverride
    ) {
        parent::__construct($attributes, $viewer, $roleTypeOverride);

        $this->courseSpec      = $courseSpec;
        $this->student         = $student;
        $this->visibility      = $visibility;
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
                    throw new UnexpectedValueException("Middle Years Task Marks must use the percentage mark type. Found '{$markScheme::getTitle()}'");
                }
            } else {
                throw new UnexpectedValueException("Middle Years Task Marks must be a Due Work, '{$assessable->getTitle()}' is an instance of {$assessable::getDefaultTypeName()}");
            }
        }

        // Does the set include an assessment imported to this class and in which the student is participating?
        $classAssessment = null;
        foreach ($cohortAssessments as $assessment) {
            if (
                $assessment->isParticipant($this->student)
                && (int)$assessment->getBaseContext()->getFolder()->getId() === (int)$this->folder->getId()
                && $this->visibility->isVisibleToUserOrChildOfUser($assessment, $this->getViewer(), true, null, false)
                && $this->isFeedbackVisibleToUser($assessment)
            ) {
                $classAssessment = $assessment;
                break;
            }
        }
        if (!$classAssessment) {
            // No assessment found, or found but not visible?
            return null;
        }

        // Ensure students participate 0:1 times per Folder per UnitSpec per ReportPeriod
        // (Note we are already in the context of a single UnitSpec and single ReportPeriod)
        $cohortAssessmentsPerFolder = $cohortAssessments->groupByCallback(static function (IAssessable $assessable) {
            return $assessable->getBaseContext()->getFolder()->getId();
        });
        /** @var int[][] $studentCountsPerFolder Indexed by folder.id, user.id */
        $studentCountsPerFolder = [];
        foreach ($cohortAssessmentsPerFolder as $folderId => $assessments) {
            $studentCountsPerFolder[$folderId] = [];
            foreach ($assessments as $assessment) {
                foreach ($assessment->getAllParticipants() as $participant) {
                    if ($participant->isExcluded()) {
                        continue;
                    }
                    $studentId = $participant->getUser()->getId();
                    if (!array_key_exists($studentId, $studentCountsPerFolder[$folderId])) {
                        $studentCountsPerFolder[$folderId][$studentId] = 0;
                    }
                    $studentCountsPerFolder[$folderId][$studentId]++;
                }
            }
        }

        $errors = [];
        foreach ($studentCountsPerFolder as $folderId => $folderStudentCounts) {
            /** @var IAssessable $firstAssessment */
            $firstAssessment = $cohortAssessmentsPerFolder[$folderId]->first();
            $folder          = $firstAssessment->getBaseContext()->getFolder();
            $folderLink      = sprintf('<a href="/homepage/%d">%s</a>', $folder->getId(), $folder->getTitle());
            foreach ($folderStudentCounts as $studentId => $count) {
                if ($count >= 2) {
                    $errors[] = "$folderLink: Student: $studentId, Count: $count";
                }
            }
        }
        if (count($errors)) {
            $msg = "Invalid data. Students may only participate in 1 <strong>7-10 Assessment Task</strong> assessment per unit per class per report period.<br>\n" .
                "Unit: <strong>{$unitSpec->getFolder()->getName()}</strong> has invalid participation across cohort.<br>\n" .
                implode("<br>\n", $errors);
            throw new UnexpectedValueException($msg);
        }

        $feedback = $this->findStudentFeedback($classAssessment);

        /* Generate some stats about this student's performance among their peers
         */
        $statistics = (new CohortStatistics(
            $this->getViewer(),
            $this->getRoleTypeOverride(),
            $cohortAssessments,
            $feedback
        ))->generate();

        /* Handy diagnosis
         */
//        new \Alaress_Probe([
//            'assessmentTitle' => $statistics['assessmentTitle'],
//            'studentMark'     => $statistics['studentMark'],
//            'cohortAverage'   => $statistics['cohortAverage'],
//            'publishAt'       => $feedback ? $feedback->getPublishedAt()->getDatabase() : null,
//            'comment'         => $feedback ? $feedback->getComment() : null,
//            'commentBy'       => $feedback ? $feedback->getReviewer()->getFullName() : null,
//            'dueDate'         => $classAssessment->getDueAt()->getDatabase(),
//            'unitSpecId'      => $unitSpec->getId(),
//            'unitSpecTitle'   => $unitSpec->getTitle(),
//        ]);

        return [
            'assessmentTitle' => $statistics['assessmentTitle'],
            'studentMark'     => $statistics['studentMark'],
            'cohortAverage'   => $statistics['cohortAverage'],
            'publishAt'       => $feedback ? $feedback->getPublishedAt()->getDatabase() : null,
            'comment'         => $feedback ? $feedback->getComment() : null,
            'commentBy'       => $feedback ? $feedback->getReviewer() : null,
            'dueDate'         => $classAssessment->getDueAt(),
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
        if (!$this->isFeedbackVisibleToUser($assessable)) {
            return null;
        }

        $participant = $assessable->getParticipantIfExtant($this->student);

        if (!$participant) {
            return null;
        }

        return $participant->getAuthoritativeFeedback(true);
    }


    /**
     * Returns all normalised marks for the cohort. Respects assessment's grade visibility
     *
     * @param GenericCollection|IAssessable[] $cohortAssessments
     * @return float[]
     */
    private function deriveCohortMarks(GenericCollection $cohortAssessments): array
    {
        $cohortMarks = [];
        foreach ($cohortAssessments as $assessable) {
            if (!$this->isFeedbackVisibleToUser($assessable)) {
                continue;
            }
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
     * Returns the average cohort mark, rounded to the specified precision
     *
     * @param float[] $cohortMarks
     * @param int     $precision
     * @return float|null
     */
    private function deriveCohortAverage(array $cohortMarks, int $precision = 2): ?float
    {
        if (!count($cohortMarks)) {
            return null;
        }
        return \round(\array_sum($cohortMarks) / count($cohortMarks), $precision);
    }
}
