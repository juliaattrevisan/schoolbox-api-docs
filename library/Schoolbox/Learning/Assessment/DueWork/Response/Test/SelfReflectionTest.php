<?php

/**
 * ValidationTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Reflection\DueWork\Response\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\ValidSelfReflection;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Quiz;
use Schoolbox\Learning\Assessment\Task;
use Schoolbox\User;

class SelfReflectionTest extends TestCase
{
    /**
     * @dataProvider provider_validSelfReflective
     * @param User        $currentUser  The user currently "logged in"
     * @param Participant $participant  The user whose trying to start self-reflection
     * @param int         $expected
     */
    public function testValidSelfReflective(User $currentUser, Participant $participant, $expected)
    {
        $validation = new ValidSelfReflection($currentUser, $participant);

        $this->assertEquals($expected, $validation->isSatisfied());
    }

    /**
     * Data provider for testValidSelfReflective
     *
     * @return array
     */
    public function provider_validSelfReflective() :array {
        $student = (new User())->setId(230);
        $otherStudent = (new User())->setId(999);

        /* This assessment is correctly set up to start Self Reflection
         */
        $correctAssessment = (new DueWork\Model())
            ->setSelfAssessable(false)
            ->setSelfReflectable(true);

        /* This assessment is incorrectly set up to start Self Reflection
         */
        $incorrectAssessment1 = (new DueWork\Model())
            ->setSelfAssessable(true)
            ->setSelfReflectable(false);

        $incorrectAssessment2 = (new DueWork\Model())
            ->setSelfAssessable(false)
            ->setSelfReflectable(false);

        $incorrectAssessment4 = (new DueWork\Model())
            ->setSelfAssessable(true)
            ->setSelfReflectable('');

        $incorrectAssessment5 = (new DueWork\Model())
            ->setSelfAssessable(null)
            ->setSelfReflectable('cat');

        /* Test everything
         */
        return [
            /* You have to be the participant
             */
            'Valid: Student starting their own self-Reflection' => [
                $student,
                new Participant($student, new Plan($correctAssessment)),
                true
            ],

            /* You can't self-assess someone else's participation
             * sorry sobo teachers
             */
            'Invalid: Student starting someone else\'s self-Reflection' => [
                $student,
                new Participant($otherStudent, new Plan($correctAssessment)),
                false
            ],

            /* These Reflections aren't configured for Self Reflection
             *
             * Shouldn't matter which logged-in user/participating user are used,
             * it should deny access regardless
             */
            'Invalid: Student starting an improperly-configured self-reflection (1)' => [
                $student,
                new Participant($student, new Plan($incorrectAssessment1)),
                false
            ],

            'Invalid: Student starting an improperly-configured self-reflection (2)' => [
                $student,
                new Participant($student, new Plan($incorrectAssessment2)),
                false
            ],

            'Invalid: Student starting an improperly-configured self-reflection (4)' => [
                $student,
                new Participant($student, new Plan($incorrectAssessment4)),
                false
            ],

            'Invalid: Student starting an improperly-configured self-reflection (5)' => [
                $student,
                new Participant($student, new Plan($incorrectAssessment5)),
                false
            ],

            'Invalid: Student starting an improperly-configured self-reflection for someone else (1)' => [
                $student,
                new Participant($otherStudent, new Plan($incorrectAssessment1)),
                false
            ],

            'Invalid: Student starting an improperly-configured self-reflection for someone else (2)' => [
                $student,
                new Participant($otherStudent, new Plan($incorrectAssessment2)),
                false
            ],

            'Invalid: Student starting an improperly-configured self-reflection for someone else (4)' => [
                $student,
                new Participant($otherStudent, new Plan($incorrectAssessment4)),
                false
            ],

            'Invalid: Student starting an improperly-configured self-reflection for someone else (5)' => [
                $student,
                new Participant($otherStudent, new Plan($incorrectAssessment5)),
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
