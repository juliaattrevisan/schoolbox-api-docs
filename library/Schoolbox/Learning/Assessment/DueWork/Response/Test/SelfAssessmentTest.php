<?php

/**
 * SelfAssessmentTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\ValidSelfAssessment;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Quiz;
use Schoolbox\Learning\Assessment\Task;
use Schoolbox\User;

class SelfAssessmentTest extends TestCase
{
    /**
     * @dataProvider provider_validSelfAssessable
     * @param User        $currentUser  The user currently "logged in"
     * @param Participant $participant  The user whose trying to start self-assessment
     * @param int         $expected
     */
    public function testValidSelfAssessable(User $currentUser, Participant $participant, $expected)
    {
        $validation = new ValidSelfAssessment($currentUser, $participant);

        $this->assertEquals($expected, $validation->isSatisfied());
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
        $correctAssessment = (new DueWork\Model())
            ->setSelfAssessable(true)
            ->setSelfReflectable(true);

        /* This assessment is incorrectly set up to start Self Assessment
         */
        $incorrectAssessment1 = (new DueWork\Model())
            ->setSelfAssessable(false)
            ->setSelfReflectable(true);

        $incorrectAssessment2 = (new DueWork\Model())
            ->setSelfAssessable(false)
            ->setSelfReflectable(false);

        /* Test everything
         */
        return [
            /* You have to be the participant
             */
            'Valid: Student starting their own self-assessment' => [
                $student,
                new Participant($student, new Plan($correctAssessment)),
                true
            ],

            /* You can't self-assess someone else's participation
             * sorry sobo teachers
             */
            'Invalid: Student starting someone else\'s self-assessment' => [
                $student,
                new Participant($otherStudent, new Plan($correctAssessment)),
                false
            ],

            /* These assessments aren't configured for Self Assessment
             *
             * Shouldn't matter which logged-in user/participating user are used,
             * it should deny access regardless
             */
            'Invalid: Student starting an improperly-configured self-assessment (1)' => [
                $student,
                new Participant($student, new Plan($incorrectAssessment1)),
                false
            ],

            'Invalid: Student starting an improperly-configured self-assessment (2)' => [
                $student,
                new Participant($student, new Plan($incorrectAssessment2)),
                false
            ],

            'Invalid: Student starting an improperly-configured self-assessment for someone else (1)' => [
                $student,
                new Participant($otherStudent, new Plan($incorrectAssessment1)),
                false
            ],

            'Invalid: Student starting an improperly-configured self-assessment for someone else (2)' => [
                $student,
                new Participant($otherStudent, new Plan($incorrectAssessment2)),
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
