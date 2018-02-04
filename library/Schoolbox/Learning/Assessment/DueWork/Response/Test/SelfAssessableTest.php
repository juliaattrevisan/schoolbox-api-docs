<?php

/**
 * SelfAssessableTest
 *
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\ValidSelfAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Quiz;
use Schoolbox\Learning\Assessment\Task;
use Schoolbox\User;
use UnexpectedValueException;

class SelfAssessableTest extends TestCase
{

    /**
     * @dataProvider provider_validSelfAssessable
     * @param User        $currentUser The user currently "logged in"
     * @param Participant $participant The user whose trying to start self-assessment
     * @param int         $expected
     * @throws UnexpectedValueException If due work model not hydrated
     */
    public function testValidSelfAssessable(User $currentUser, Participant $participant, $expected)
    {
        $validation = new ValidSelfAssessable();

        $this->assertEquals($expected, $validation->isSatisfiedBy($currentUser, $participant));
    }

    /**
     * Data provider for testValidSelfAssessable
     *
     * @return array
     */
    public function provider_validSelfAssessable() :array {
        $student = (new User())->setId(230);
        $otherStudent = (new User())->setId(999);

        /* This assessment is correctly set up to start Self Assessment
         */
        $correctAssessment1 = (new DueWork\Model())
            ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_FULL);

        /* This assessment is also set up to start Self Assessment
         */
        $correctAssessment2 = (new DueWork\Model())
            ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_REFLECT_ONLY);

        $incorrectAssessment = (new DueWork\Model())
            ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_NONE);

        /* Test everything
         */
        return [
            /* You have to be the participant
             */
            'Valid: Student starting their own self-assessment' => [
                $student,
                new Participant($student, new Plan($correctAssessment1)),
                true
            ],

            /* You can't self-assess someone else's participation
             * sorry sobo teachers
             */
            'Invalid: Student starting someone else\'s self-assessment' => [
                $student,
                new Participant($otherStudent, new Plan($correctAssessment1)),
                false
            ],

            /* These assessments aren't configured for Self Assessment
             *
             * Shouldn't matter which logged-in user/participating user are used,
             * it should deny access regardless
             */
            'Invalid: Student starting an improperly-configured self-assessment (1)' => [
                $student,
                new Participant($student, new Plan($correctAssessment2)),
                true
            ],

            'Invalid: Student starting an improperly-configured self-assessment (2)' => [
                $student,
                new Participant($student, new Plan($incorrectAssessment)),
                false
            ],

            'Invalid: Student starting an improperly-configured self-assessment for someone else (1)' => [
                $student,
                new Participant($otherStudent, new Plan($correctAssessment2)),
                false
            ],

            'Invalid: Student starting an improperly-configured self-assessment for someone else (2)' => [
                $student,
                new Participant($otherStudent, new Plan($incorrectAssessment)),
                false
            ],

            /* These assessment types cannot be used at all
             */
            'Invalid: Wrong assessment type (1)' => [
                $student,
                new Participant($student, new Plan(new Quiz\Model())),
                false
            ],

            'Invalid: Wrong assessment type (2)' => [
                $student,
                new Participant($student, new Plan(new Task\Model())),
                false
            ],
        ];
    }
}
