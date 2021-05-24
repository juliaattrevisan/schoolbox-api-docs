<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport\Components;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;

/**
 * Class CohortStatistics
 *
 * From a given student mark and a set of assessments undertaken by the cohort,
 * find out some basic statistics data for that given student so you can see how
 * they fared vs their peers
 *
 * - the assessment which has their mark in it
 * - the mark they received
 * - the accompanying comment
 * - the average cohort mark
 * - the student's rank among the cohort
 *
 * Their rank expressed positionally, like this: 3/7
 * That means they got the third best mark out of seven students
 *
 * @package Custom\Tigs\Summative\SubjectReport\Components
 */
class CohortStatistics
{

    /**
     * @var \Schoolbox\User
     */
    private $viewer;


    /**
     * @var string|null
     */
    private $roleTypeOverride;


    /**
     * @var GenericCollection
     */
    private $cohortAssessments;


    /**
     * @var IFeedback|null
     */
    private $feedback;


    /**
     * CohortStatistics constructor.
     *
     * @param \Schoolbox\User   $viewer
     * @param string|null       $roleTypeOverride
     * @param GenericCollection $cohortAssessments
     * @param IFeedback|null    $feedback
     */
    public function __construct(
        \Schoolbox\User $viewer,
        ?string $roleTypeOverride,
        GenericCollection $cohortAssessments,
        ?IFeedback $feedback
    ) {
        $this->viewer = $viewer;
        $this->roleTypeOverride = $roleTypeOverride;
        $this->cohortAssessments = $cohortAssessments;
        $this->feedback = $feedback;
    }


    /**
     * @return array
     */
    public function generate(): array
    {
        $normalisedMark = $this->feedback ? $this->feedback->getNormalisedMark() : null;

        $cohortMarks = $this->deriveCohortMarks($this->cohortAssessments);
        $cohortRank = $this->deriveCohortRank($normalisedMark, $cohortMarks);
        $cohortAverage = $this->deriveCohortAverage($cohortMarks);
        $cohortCount = \count($cohortMarks);

        /* Figure out the three pieces you need and represent them as simple string values
         * stored in a tuple:
         *
         * The assessment name containing the Student Mark
         * Student Mark
         * Comment
         * Cohort Average
         * Cohort Rank
         */
        return [
            'assessmentTitle' => null === $this->feedback ? null : $this->feedback->getAssessable()->getTitle(),
            'studentMark' => null === $this->feedback ? null : $this->feedback->getHumanReadableMark(),
            'comment' => null === $this->feedback ? null : $this->feedback->getComment(),
            'cohortAverage' => null === $cohortAverage ? null : "{$cohortAverage} %",
            'cohortRank' => null === $cohortRank ? null : "{$cohortRank} / {$cohortCount}",
        ];
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
        if (!\count($cohortMarks)) {
            return null;
        }
        return \round(\array_sum($cohortMarks) / \count($cohortMarks), $precision);
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

        /* you can never be rank 0
         */
        return $index + 1;
    }


    /**
     * Returns whether the specified assessment's feedback should be visible for the current viewer and role-type override
     *
     * @param IAssessable $assessable
     * @return bool
     */
    private function isFeedbackVisibleToUser(IAssessable $assessable): bool
    {
        return $assessable->isFeedbackVisibleToRoleType($this->roleTypeOverride ?? $this->viewer->getRole()->getType());
    }
}
