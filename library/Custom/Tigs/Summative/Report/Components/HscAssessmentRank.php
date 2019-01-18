<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report\Components;

use Custom\Tigs\Summative\Report\StudentAttributes;
use Custom\Tigs\Summative\Report\BaseComponent;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use UnexpectedValueException;

class HscAssessmentRank extends BaseComponent
{

    /** @var GenericCollection|IAssessable[] */
    private $cohortAssessments;

    /** @var User */
    private $student;

    /** @var Visibility */
    private $visibility;

    /** @var User */
    private $viewer;

    /** @var Folder\Model */
    private $folder;


    /**
     * Constructor
     *
     * @param GenericCollection|IAssessable[] $cohortAssessments
     * @param User                            $student
     * @param StudentAttributes               $attributes
     * @param Visibility                      $visibility
     * @param User                            $viewer
     * @param Folder\Model                    $folder
     */
    public function __construct(
        GenericCollection $cohortAssessments,
        User $student,
        StudentAttributes $attributes,
        Visibility $visibility,
        User $viewer,
        Folder\Model $folder
    ) {
        parent::__construct($attributes);

        $this->cohortAssessments = $cohortAssessments;
        $this->student           = $student;
        $this->visibility        = $visibility;
        $this->viewer            = $viewer;
        $this->folder            = $folder;
    }


    /**
     * Returns an array indexed by unitSpec.id
     *
     * @return array
     * @throws UnexpectedValueException
     */
    public function toContext(): array
    {
        // Group cohort assessments per class
        /** @var Collection|GenericCollection[]|IAssessable[][] $cohortAssessmentsPerClass */
        $cohortAssessmentsPerClass = $this->cohortAssessments->groupByCallback(function (IAssessable $assessable) {
            return $assessable->getBaseContext()->getFolder()->getId();
        });

        // Check that this class has an instance of the expected work type
        $classAssessments = $cohortAssessmentsPerClass[$this->folder->getId()];
        if (!$classAssessments) {
            throw new UnexpectedValueException("No 'HSC Assessment Rank' assessment found for class");
        }

        foreach ($cohortAssessmentsPerClass as $folderId => $classAssessments) {
            // Check each class has only a single instance
            if ($classAssessments->count() !== 1) {
                /** @var IAssessable $classAssessment */
                $classAssessment = $classAssessments->first();
                $classFolder     = $classAssessment->getBaseContext()->getFolder();
                throw new UnexpectedValueException("A class may only have 1 'HSC Assessment Rank' assessment. {$classFolder->getTitle()} has {$classAssessments->count()}");
            }
            // Check all instances of the configured work type are of correct IAssessable type, and Mark Type
            foreach ($classAssessments as $classAssessment) {
                if (!$classAssessment instanceof DueWork\Model) {
                    throw new UnexpectedValueException("A 'HSC Assessment Rank' assessment must be a Due Work. '{$classAssessment->getTitle()}' is a '{$classAssessment::getDefaultTypeName()}'");
                }
                $markScheme = $classAssessment->getMarkScheme();
                if (!$markScheme instanceof Percentage) {
                    throw new UnexpectedValueException("A 'HSC Assessment Rank' assessment must have a mark type of 'Percentage'. '{$classAssessment->getTitle()}' uses '{$markScheme::getTitle()}'");
                }
            }
        }

        $feedback       = $this->findStudentFeedback($cohortAssessmentsPerClass[$this->folder->getId()]->first());
        $normalisedMark = $feedback ? $feedback->getNormalisedMark() : null;
        $cohortMarks    = $this->deriveCohortMarks($this->cohortAssessments);
        $cohortRank     = $this->deriveCohortRank($normalisedMark, $cohortMarks);
        $countCohort    = \count($cohortMarks);
        $cohortAverage  = $this->deriveCohortAverage($cohortMarks);

        // Both Yr11 + 12 see this datum
        $data = ['assessmentRank' => null === $cohortRank ? null : "$cohortRank / $countCohort"];

        // But only Yr11 see these datums
        if (11 === $this->getAttributes()->getCurriculumYearLevel()) {
            $data = \array_merge($data, [
                'assessmentMark'    => $feedback ? $feedback->getHumanReadableMark() : null,
                'assessmentAverage' => null === $cohortAverage ? null : "$cohortAverage %",
            ]);
        }
        return $data;
    }


    /**
     * Returns the student's authoritative feedback for the assessment.
     *
     * Returns NULL if assessment or feedback is not visible.
     *
     * @param IAssessable $assessable
     * @return IFeedback|null
     */
    private function findStudentFeedback(IAssessable $assessable): ?IFeedback
    {
        if (!$this->visibility->isVisibleToUserOrChildOfUser($assessable, $this->viewer, true, null, false)) {
            return null;
        }
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
