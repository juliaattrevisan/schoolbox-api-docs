<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Quiz\Test;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Participant\Plan\Mapper as PlanMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Project\ResultCache;
use Schoolbox\Learning\Assessment\Quiz;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Quiz\ActionValidator;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Tests quiz action visibility decisions.
 */
class ActionValidatorTest extends TestCase
{
    private const string NOW_DATABASE = '2010-01-10 00:00:00';

    /**
     * Ensures parent quiz visibility is denied when the quiz is not parent-visible.
     */
    public function testParentCannotSeeQuizWhenParentVisibilityFailsEvenIfChildCanSeeIt(): void
    {
        $now = new Alaress_Date_Format(self::NOW_DATABASE, Alaress_Date_Format::DATABASE_FORMAT);

        $child = (new User())
            ->setId(3)
            ->setRole((new Role())->setType(Role::STUDENT));
        $parent = (new User())
            ->setId(4)
            ->setRole((new Role())->setType(Role::PARENT))
            ->setChildren(new GenericCollection(User::class, [$child]));

        $quiz = (new Quiz\Model())
            ->setId(1)
            ->setTitle('A Quiz')
            ->setContext((new Folder\Model())->setId(1))
            ->setWorkType(
                (new WorkType\Model())
                    ->setIsVisibleToParents(false)
                    ->setIsVisibleToLearners(true)
            )
            ->setPublishAt((clone $now)->addDays(-1))
            ->setOpenAt((clone $now)->addDays(-1))
            ->setDueAt((clone $now)->addDays(1))
            ->setCloseAt((clone $now)->addDays(1));

        $plan = (new Plan($quiz))->setIsDynamic(PlanMapper::PLAN_TYPE_DYNAMIC);
        $plan->getParticipants()[$child->getId()] = new Participant(
            $child,
            $plan,
            ResultCache\Mapper::getInstance()
        );
        $quiz->setPlans($quiz->getPlans()->emptyClone()->add($plan));

        $validator = new ActionValidator($now, $this->getAccessStub($child));

        $this->assertFalse($validator->canSeeQuiz($parent, $quiz));
    }

    /**
     * Builds an access stub that treats only the supplied child as a learner.
     */
    private function getAccessStub(User $child): Access
    {
        $access = $this->createStub(Access::class);
        $access
            ->method('isInstructor')
            ->willReturn(false);
        $access
            ->method('isLearner')
            ->willReturnCallback(
                /**
                 * Reports learner access only for the configured child user.
                 */
                static function (User $user) use ($child) {
                    return $user->getId() === $child->getId();
                }
            );
        $access
            ->method('getChildLearnersOfParent')
            ->willReturn(new GenericCollection(User::class, [$child]));

        return $access;
    }
}
