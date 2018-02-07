<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Participant\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Participant\AssessmentListMutator;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\User;

class AssessmentListMutatorTest extends TestCase
{
    public function testUser()
    {
        // Set up assessment list
        $user        = (new User())->setId(9);
        $assessment1 = new DueWork\Model();
        $plan1       = new Plan($assessment1);
        $plan1->getParticipants()
              ->setOffset(9, new Participant($user, $plan1));
        $assessment1
            ->setId(1)
            ->getPlans()->add($plan1)
        ;
        $assessment2 = new DueWork\Model();
        $plan2       = new Plan($assessment2);
        $plan2->getParticipants()
              ->setOffset(9, new Participant($user, $plan2));
        $assessment2
            ->setId(2)
            ->getPlans()->add($plan1)
        ;

        $assessments = (new GenericCollection(IAssessable::class))
            ->setOffset(1, $assessment1)
            ->setOffset(2, $assessment2);

        // mock of visibility: pretend all visible
        $mockVisibility = $this->createMock(Visibility::class);
        $mockVisibility
            ->expects($this->any())
            ->method('isVisibleToUser')
            ->willReturn(true);

        $mutator = new AssessmentListMutator(
            new User(),
            $mockVisibility,
            \Alaress_Date_Format::now(),
            true
        );

        $mappedAssessments = $assessments->map([$mutator, 'map']);
        $this->assertCount(2, $mappedAssessments);
        $this->assertCount(1, $mappedAssessments[1]->getAllParticipants());
        $this->assertCount(1, $mappedAssessments[2]->getAllParticipants());

        $filteredAssessments = $mappedAssessments->filter([$mutator, 'filter']);
        $this->assertCount(2, $filteredAssessments);


        // mock of visibility: pretend all visible
        $mockInvisibility = $this->createMock(Visibility::class);
        $mockInvisibility
            ->expects($this->any())
            ->method('isVisibleToUser')
            ->willReturn(false);

        $mutator = new AssessmentListMutator(
            new User(),
            $mockInvisibility,
            \Alaress_Date_Format::now(),
            true
        );

        $mappedAssessments = $assessments->map([$mutator, 'map']);
        $this->assertCount(0, $mappedAssessments);

        $filteredAssessments = $mappedAssessments->filter([$mutator, 'filter']);
        $this->assertCount(0, $filteredAssessments);

    }

    public function testParentOfUser()
    {
        //
    }
}
