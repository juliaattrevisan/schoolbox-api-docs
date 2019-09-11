<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Participant\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
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

    /**
     * @var GenericCollection|IAssessable[]
     */
    private $assessments;

    /**
     * Set up student, parent, user methods
     */
    public function setUp()
    {
        $this->student = (new User())->setId(9)->setChildren(new GenericCollection(User::class));
        $this->parent  = (new User())->setId(10)->setChildren(new GenericCollection(User::class));
        $this->parent->getChildren()->setOffset($this->student->getId(), $this->student);

        // Set up assessments
        $assessment1 = new DueWork\Model();
        $plan1       = new Plan($assessment1);
        $plan1->getParticipants()
              ->setOffset($this->student->getId(), new Participant($this->student, $plan1));
        $assessment1
            ->setId(1)
            ->getPlans()->add($plan1)
        ;
        $assessment2 = new DueWork\Model();
        $plan2       = new Plan($assessment2);
        $plan2->getParticipants()
              ->setOffset($this->student->getId(), new Participant($this->student, $plan2));
        $assessment2
            ->setId(2)
            ->getPlans()->add($plan2)
        ;

        $this->assessments = (new GenericCollection(IAssessable::class))
            ->setOffset($assessment1->getId(), $assessment1)
            ->setOffset($assessment2->getId(), $assessment2);
    }

    /**
     * Test list mutator as a participant.
     *
     * @throws \PHPUnit_Framework_MockObject_RuntimeException
     * @throws \PHPUnit\Framework\Exception
     */
    public function testUser(): void
    {
        // mock of visibility: pretend 1 visible, 2 not
        /** @var Visibility|PHPUnit_Framework_MockObject_MockObject $mockVisibility */
        $mockVisibility = $this->createMock(Visibility::class);
        $mockVisibility
            ->expects($this->any())
            ->method('isVisibleToUser')
            ->willReturnCallback(function (IAssessable $v) {
                return $v->getId() === 1;
            });

        $mutator = new AssessmentListMutator($this->student, $mockVisibility, \Alaress_Date_Format::now(), true);

        // Mapping: won't have filtered any participants
        $mappedAssessments = $this->assessments->map([$mutator, 'map']);
        $this->assertCount(2, $mappedAssessments);
        $this->assertCount(1, $mappedAssessments[1]->getAllParticipants());
        $this->assertCount(1, $mappedAssessments[2]->getAllParticipants());

        // Filtering: has filtered out assessment 2, as $student isn't participating
        $filteredAssessments = $mappedAssessments->filter([$mutator, 'filter']);
        $this->assertCount(1, $filteredAssessments);
        $this->assertArrayHasKey(1, $filteredAssessments);
        $this->assertArrayNotHasKey(2, $filteredAssessments);
    }


    /**
     * Test list mutator as the parent of a participant.
     *
     * @throws \PHPUnit_Framework_MockObject_RuntimeException
     * @throws \PHPUnit\Framework\Exception
     */
    public function testParentOfUser(): void
    {
        // mock of visibility: pretend 2 visible, 1 not
        /** @var Visibility|PHPUnit_Framework_MockObject_MockObject $mockVisibility */
        $mockVisibility = $this->createMock(Visibility::class);
        $mockVisibility
            ->expects($this->any())
            ->method('isVisibleToUser')
            ->willReturnCallback(function (IAssessable $v) {
                return $v->getId() === 2;
            });

        $mutator = new AssessmentListMutator($this->parent, $mockVisibility, \Alaress_Date_Format::now(), true);

        // Mapping: has filtered out $parent's child from assessment 1
        $mappedAssessments = $this->assessments->map([$mutator, 'map']);
        $this->assertCount(2, $mappedAssessments);
        $this->assertCount(0, $mappedAssessments[1]->getAllParticipants());
        $this->assertCount(1, $mappedAssessments[2]->getAllParticipants());

        // Filtering: has filtered out assessment 1, as $student isn't participating
        $filteredAssessments = $mappedAssessments->filter([$mutator, 'filter']);
        $this->assertCount(1, $filteredAssessments);
        $this->assertArrayNotHasKey(1, $filteredAssessments);
        $this->assertArrayHasKey(2, $filteredAssessments);
    }
}
