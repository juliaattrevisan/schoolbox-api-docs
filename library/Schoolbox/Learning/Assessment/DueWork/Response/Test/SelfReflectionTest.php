<?php

/**
 * SelfReflectionTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\ValidSelfReflection;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Project\ResultCache;
use Schoolbox\Learning\Assessment\Quiz;
use Schoolbox\Learning\Assessment\Task;
use Schoolbox\User;

class SelfReflectionTest extends TestCase
{
    /**
     * @param User        $currentUser  The user currently "logged in"
     * @param Participant $participant  The user whose trying to start self-reflection
     * @param int         $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidSelfReflective')]
    public function testValidSelfReflective(User $currentUser, Participant $participant, $expected)
    {
        $validation = new ValidSelfReflection();

        $this->assertEquals($expected, $validation->isSatisfiedBy($currentUser, $participant));
    }

    /**
     * Data provider for testValidSelfReflective
     *
     * @return array
     */
    public static function providerValidSelfReflective(): array
    {
        $student = (new User())->setId(230);
        $otherStudent = (new User())->setId(999);

        /* This assessment is correctly set up to start Self Reflection
         */
        $correctAssessment = (new DueWork\Model())
            ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_REFLECT_ONLY);

        /* This assessment is incorrectly set up to start Self Reflection
         */
        $incorrectAssessment1 = (new DueWork\Model())
            ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_FULL);

        $incorrectAssessment2 = (new DueWork\Model())
            ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_NONE);

        $resultCacheMapper = ResultCache\Mapper::getInstance();

        /* Test everything
         */
        return [
            /* You have to be the participant
             */
            'Valid: Student starting their own self-Reflection' => [
                $student,
                new Participant($student, new Plan($correctAssessment), $resultCacheMapper),
                true
            ],

            /* You can't self-assess someone else's participation
             * sorry sobo teachers
             */
            'Invalid: Student starting someone else\'s self-Reflection' => [
                $student,
                new Participant($otherStudent, new Plan($correctAssessment), $resultCacheMapper),
                false
            ],

            /* These Reflections aren't configured for Self Reflection
             *
             * Shouldn't matter which logged-in user/participating user are used,
             * it should deny access regardless
             */
            'Invalid: Student starting an improperly-configured self-reflection (1)' => [
                $student,
                new Participant($student, new Plan($incorrectAssessment1), $resultCacheMapper),
                false
            ],

            'Invalid: Student starting an improperly-configured self-reflection (2)' => [
                $student,
                new Participant($student, new Plan($incorrectAssessment2), $resultCacheMapper),
                false
            ],

            'Invalid: Student starting an improperly-configured self-reflection for someone else (1)' => [
                $student,
                new Participant($otherStudent, new Plan($incorrectAssessment1), $resultCacheMapper),
                false
            ],

            'Invalid: Student starting an improperly-configured self-reflection for someone else (2)' => [
                $student,
                new Participant($otherStudent, new Plan($incorrectAssessment2), $resultCacheMapper),
                false
            ],

            /* These assessment types cannot be used at all
             */
            'Invalid: Wrong assessment type (1)' => [
                $student,
                new Participant($student, new Plan(new Quiz\Model()), $resultCacheMapper),
                false
            ],

            'Invalid: Wrong assessment type (2)' => [
                $student,
                new Participant($student, new Plan(new Task\Model()), $resultCacheMapper),
                false
            ],
        ];
    }
}
