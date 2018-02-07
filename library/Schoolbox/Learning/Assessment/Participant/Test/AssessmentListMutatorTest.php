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
    /**
     * @var User
     */
    private $student;

    /**
     * @var User
     */
    private $parent;

    public function setUp()
    {
        $this->student = (new User())->setId(9);
        $this->parent  = (new User())->setId(10);
        $this->parent->getChildren()->setOffset(9, $this->student);
    }

    /**
     * Test list mutator as a participant.
     */
    public function testUser()
    {
        // Set up assessment list
        $assessment1 = new DueWork\Model();
        $plan1       = new Plan($assessment1);
        $plan1->getParticipants()
              ->setOffset(9, new Participant($this->student, $plan1));
        $assessment1
            ->setId(1)
            ->getPlans()->add($plan1)
        ;
        $assessment2 = new DueWork\Model();
        $plan2       = new Plan($assessment2);
        $plan2->getParticipants()
              ->setOffset(9, new Participant($this->student, $plan2));
        $assessment2
            ->setId(2)
            ->getPlans()->add($plan2)
        ;

        $assessments = (new GenericCollection(IAssessable::class))
            ->setOffset(1, $assessment1)
            ->setOffset(2, $assessment2);

        // mock of visibility: pretend 1 visible, 2 not
        $mockVisibility = $this->createMock(Visibility::class);
        $mockVisibility
            ->expects($this->any())
            ->method('isVisibleToUser')
            ->willReturnCallback(function (IAssessable $v) {
                return $v->getId() === 1;
            });
        $mockVisibility
            ->expects($this->any())
            ->method('isVisibleToLearner')
            ->willReturn(false);

        $mutator = new AssessmentListMutator($this->student, $mockVisibility, \Alaress_Date_Format::now(), true);

        $mappedAssessments = $assessments->map([$mutator, 'map']);
        $this->assertCount(2, $mappedAssessments);

        $filteredAssessments = $mappedAssessments->filter([$mutator, 'filter']);
        $this->assertCount(1, $filteredAssessments);
        $this->assertArrayHasKey(1, $filteredAssessments);
        $this->assertArrayNotHasKey(2, $filteredAssessments);
    }

    /**
     * Test list mutator as the parent of a participant.
     */
    public function testParentOfUser()
    {
        $assessment1 = new DueWork\Model();
        $plan1       = new Plan($assessment1);
        $plan1->getParticipants()
              ->setOffset(9, new Participant($this->student, $plan1));
        $assessment1
            ->setId(1)
            ->getPlans()->add($plan1)
        ;
        $assessment2 = new DueWork\Model();
        $plan2       = new Plan($assessment2);
        $plan2->getParticipants()
              ->setOffset(9, new Participant($this->student, $plan2));
        $assessment2
            ->setId(2)
            ->getPlans()->add($plan2)
        ;

        $assessments = (new GenericCollection(IAssessable::class))
            ->setOffset(1, $assessment1)
            ->setOffset(2, $assessment2);

        // mock of visibility: pretend 1 visible, 2 not
        $mockVisibility = $this->createMock(Visibility::class);
        $mockVisibility
            ->expects($this->any())
            ->method('isVisibleToUser')
            ->willReturn(false);
        $mockVisibility
            ->expects($this->any())
            ->method('isVisibleToLearner')
            ->willReturnCallback(function (IAssessable $v) {
                return $v->getId() === 1;
            });;

        $mutator = new AssessmentListMutator($this->parent, $mockVisibility, \Alaress_Date_Format::now(), true);

        $mappedAssessments = $assessments->map([$mutator, 'map']);
        $this->assertCount(2, $mappedAssessments);
        $this->assertCount(1, $mappedAssessments[1]->getAllParticipants());
        $this->assertCount(0, $mappedAssessments[2]->getAllParticipants());

        $filteredAssessments = $mappedAssessments->filter([$mutator, 'filter']);
        $this->assertCount(1, $filteredAssessments);
        $this->assertArrayHasKey(1, $filteredAssessments);
        $this->assertArrayNotHasKey(2, $filteredAssessments);
    }
}
