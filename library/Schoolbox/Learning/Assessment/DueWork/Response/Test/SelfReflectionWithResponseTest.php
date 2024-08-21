<?php

/**
 * SelfReflectionWithResponseTest
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
use Schoolbox\Learning\Assessment\DueWork\Response\ValidSelfReflectionWithResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Project\ResultCache;
use Schoolbox\User;

class SelfReflectionWithResponseTest extends TestCase
{
    /**
     * @param User        $currentUser  The user currently "logged in"
     * @param Participant $participant  The user whose trying to start self-assessment
     * @param int         $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidSelfReflectable')]
    public function testValidSelfAssessable(User $currentUser, Participant $participant, $expected)
    {
        $validation = new ValidSelfReflectionWithResponse();

        $this->assertEquals($expected, $validation->isSatisfiedBy($currentUser, $participant));
    }

    /**
     * Data provider for testValidSelfReflectable
     *
     * @return array
     */
    public static function providerValidSelfReflectable(): array
    {
        $resultCacheMapper = ResultCache\Mapper::getInstance();

        $student = (new User())->setId(230);

        $correctAssessment = (new DueWork\Model())
            ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_REFLECT_ONLY);

        /* This participation is correctly set up to conclude Self Assessment
         */
        $correctParticipation = new Participant($student, new Plan($correctAssessment), $resultCacheMapper);
        $correctParticipation->getAllResponses()->add(new DueWork\Response\Model());

        /* This participation is incorrectly set up: no draft response exists
         */
        $incorrectParticipation1 = new Participant($student, new Plan($correctAssessment), $resultCacheMapper);

        $incorrectParticipation2 = new Participant($student, new Plan($correctAssessment), $resultCacheMapper);
        $incorrectParticipation2->getAllResponses()->add(
            (new DueWork\Response\Model())->setSubmittedAt(\Alaress_Date_Format::now())
        );

        /* Test everything
         */
        return [
            /* You have to be the participant
             */
            'Valid: Student concluding their own self-reflection' => [
                $student,
                $correctParticipation,
                true
            ],

            'Invalid: No draft response exists for this participation (1)' => [
                $student,
                $incorrectParticipation1,
                false
            ],

            'Invalid: No draft response exists for this participation (2)' => [
                $student,
                $incorrectParticipation2,
                false
            ],
        ];
    }
}
