<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Participant\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Participant\AssessmentListMutator;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Project\ResultCache;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Role;
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
    public function setUp(): void
    {
        $resultCacheMapper = ResultCache\Mapper::getInstance();

        // Create mock student user.
        $studentRole = new Role();
        $studentRole->setType(Role::STUDENT);
        $this->student = (new User())->setId(9)
            ->setRole($studentRole)
            ->setChildren(new GenericCollection(User::class));

        // Create mock parent user with child.
        $parentRole = new Role();
        $parentRole->setType(Role::PARENT);
        $this->parent = (new User())->setId(10)
            ->setRole($parentRole)
            ->setChildren(new GenericCollection(User::class));
        $this->parent->getChildren()->setOffset($this->student->getId(), $this->student);

        // Set up assessments
        $assessment1 = new DueWork\Model();
        $plan1       = new Plan($assessment1);
        $plan1->getParticipants()
              ->setOffset($this->student->getId(), new Participant($this->student, $plan1, $resultCacheMapper));
        $assessment1
            ->setId(1)
            ->getPlans()->add($plan1)
        ;
        $assessment2 = new DueWork\Model();
        $plan2       = new Plan($assessment2);
        $plan2->getParticipants()
              ->setOffset($this->student->getId(), new Participant($this->student, $plan2, $resultCacheMapper));
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
     * @throws \PHPUnit\Framework\Exception
     */
    public function testUser(): void
    {
        // mock of visibility: pretend 1 visible, 2 not
        $mockVisibility = $this->createStub(Visibility::class);
        $mockVisibility
            ->method('isVisibleToUser')
            ->willReturnCallback(function (IAssessable $v) {
                return $v->getId() === 1;
            });

        $mutator = new AssessmentListMutator(
            $this->student,
            $mockVisibility,
            \Alaress_Date_Format::now(),
            true
        );

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
     * @throws \PHPUnit\Framework\Exception
     */
    public function testParentOfUser(): void
    {
        // mock of visibility: pretend 2 visible, 1 not
        $mockVisibility = $this->createStub(Visibility::class);
        $mockVisibility
            ->method('isVisibleToUser')
            ->willReturnCallback(function (IAssessable $v) {
                return $v->getId() === 2;
            });

        $mutator = new AssessmentListMutator(
            $this->parent,
            $mockVisibility,
            \Alaress_Date_Format::now(),
            true
        );

        // Mapping: pre-computes visibility without mutating participants.
        $mappedAssessments = $this->assessments->map([$mutator, 'map']);
        $this->assertCount(2, $mappedAssessments);
        $this->assertCount(1, $mappedAssessments[1]->getAllParticipants());
        $this->assertCount(1, $mappedAssessments[2]->getAllParticipants());

        // Filtering: has filtered out assessment 1, as the parent can't view it.
        $filteredAssessments = $mappedAssessments->filter([$mutator, 'filter']);
        $this->assertCount(1, $filteredAssessments);
        $this->assertArrayNotHasKey(1, $filteredAssessments);
        $this->assertArrayHasKey(2, $filteredAssessments);
    }


    public function testParentVisibilityIsNotReplacedByChildVisibility(): void
    {
        $mockVisibility = $this->createStub(Visibility::class);
        $mockVisibility
            ->method('isVisibleToUser')
            ->willReturnCallback(function (IAssessable $assessment, User $viewer) {
                return $viewer->getId() === $this->student->getId();
            });

        $mutator = new AssessmentListMutator(
            $this->parent,
            $mockVisibility,
            \Alaress_Date_Format::now(),
            true
        );

        $mappedAssessments   = $this->assessments->map([$mutator, 'map']);
        $filteredAssessments = $mappedAssessments->filter([$mutator, 'filter']);

        $this->assertCount(0, $filteredAssessments);
    }
}
