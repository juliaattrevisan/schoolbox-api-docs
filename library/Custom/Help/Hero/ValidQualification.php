<?php
/**
 * ValidQualification
 *
 * The necessary assessments for accomplishing the Hero award
 * The conditions which fulfil the Hero award
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Help\Hero;

class ValidQualification
{

    /**
     * Due Work
     *
     * A list of assessment spec ids that are mandatory for the Hero award
     * There are some optional Due Works that don't contribute toward the achievement
     */
    public const MANDATORY_DUE_WORKS = [
        15574, // Unit 4, Project 3 Advanced Course Skills
        15607, // Unit 5, Project 2 Master Courses And Classes
        15635, // Unit 7, Project 3 Managing Groups And Pages
    ];

    /**
     * Quizzes
     *
     * A list of assessment spec ids that are mandatory for the Hero award
     */
    public const MANDATORY_QUIZZES = [
        16058, // Unit 1, Project 2 Prove yourself as a Schoolbox Teacher Newbie
        15912, // Unit 2, Project 1 Review your knowledge of the calendar
        55313, // Unit 2, Project 2 Verify your understanding of news
        15509, // Unit 3, Project 2 Verify your knowledge of page types and components
        15768, // Unit 6, Project 1 Prove your knowledge about submitting and marking
        16911, // Unit 7, Project 1 Test your knowledge of advanced component usage
        15913, // Unit 7, Project 2 Test your knowledge of creating pages
        15772, // Unit 8, Project 1 Complete this continuous reporting quiz

        /* I don't think these are mandatory for the achievement
         */
        // 43618, // Unit 9 Review your knowledge of v17
        // 46375, // Unit 9 Review your knowledge of v17.5
    ];

    /**
     * Quizzes
     *
     * A list of assessment spec ids that are conditional
     *
     * @var array
     */
    public const CONDITIONAL_QUIZZES = [
        15767, // Unit 3, Project 1 Assess your knowledge of the timetable
        15766, // Unit 3, Project 1 Assess your knowledge of the timetable and marking attendance
    ];

    /**
     * Quizzes
     *
     * A list of assessment spec ids that are conditional, along with their counterpart
     * An organised format of CONDITIONAL_QUIZZES
     *
     * @var array
     */
    private const CONDITIONAL_QUIZ_PAIRS = [
        15767 => 15766
    ];

    /**
     * $mark is equivalent to 100.00%
     *
     * @param $mark
     * @return bool
     */
    private function isFullmarks($mark): bool
    {
        return (float)$mark === (float)100;
    }

    /**
     * Determine if $userData fulfilled the Hero Award requirements
     *
     * @param array $userData
     * @return bool
     */
    public function isSatisfiedBy(array $userData): bool
    {
        /** @var array $assessments */
        $assessments = $userData['assessments'];

        /* All Due Works must be 100%
         */
        $mandatoryDueWorksSatisfied = 0;

        foreach (self::MANDATORY_DUE_WORKS as $specId) {
            if ($this->isFullmarks($assessments[$specId]['mark'])) {
                $mandatoryDueWorksSatisfied++;
            }
        }

        /* All mandatory quizzes must be 100%
         */
        $mandatoryQuizSatisfied = 0;

        foreach (self::MANDATORY_QUIZZES as $specId) {
            if ($this->isFullmarks($assessments[$specId]['mark'])) {
                $mandatoryQuizSatisfied++;
            }
        }

        /* One of each of the conditional pairs must be 100%
         */
        $conditionalQuizSatisfied = 0;

        foreach (self::CONDITIONAL_QUIZ_PAIRS as $opt1 => $opt2) {
            if ($this->isFullmarks($assessments[$opt1]['mark']) || $this->isFullmarks($assessments[$opt2]['mark'])) {
                $conditionalQuizSatisfied++;
            }
        }

        /* Compare counts: victory condition
         */
        $mandatoryDueWorks = \count(self::MANDATORY_DUE_WORKS) === $mandatoryDueWorksSatisfied;
        $mandatoryQuizzes = \count(self::MANDATORY_QUIZZES) === $mandatoryQuizSatisfied;
        $conditionalQuizzes = \count(self::CONDITIONAL_QUIZ_PAIRS) === $conditionalQuizSatisfied;

        return $mandatoryDueWorks && $mandatoryQuizzes && $conditionalQuizzes;
    }
}
