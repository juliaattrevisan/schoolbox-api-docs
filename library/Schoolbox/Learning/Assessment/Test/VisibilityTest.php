<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Test;

use PHPUnit\Framework\TestCase;
use Alaress_Date_Format;
use Schoolbox\Assessment\Participant\Plan\Mapper as PlanMapper;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Project\ResultCache;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use Schoolbox\User;
use InvalidArgumentException;
use PHPUnit;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\RuntimeException;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Resource\Folder;

/**
 * This class tests whether a user can see an assessment by considering the following factors:
 * 1. isParticipant: [true, false]
 * 2. isStaff:       [true, false]
 * 3. isInstructor:  [DENY, WRITE]
 * 4. workType:      [none, staff-instructors-learners-parents, staff-instructors-parents, staff-instructors-learners, staff-instructors]
 * 5. showClosed:    [true, false]
 * 6. state:         [unpublished, upcoming, open, overdue, closed]
 *
 * For simplicity, we assume all tests are in the context of a single folder,
 * and will vary user and assessment attributes to cover all combinations.
 */
class VisibilityTest extends TestCase
{
    /** @var string */
    private const NOW_DATABASE = '2010-01-10 00:00:00';    // To ensure test repeatability, freeze time

    /**
     * @param bool                $isParticipant
     * @param Role                $role
     * @param User                $user
     * @param null|WorkType\Model $workType
     * @param IAssessable         $assessable
     * @param bool                $showClosed
     * @param Alaress_Date_Format $now
     * @param bool                $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsVisible')]
    public function testIsVisible(
        bool $isParticipant,
        Role $role,
        User $user,
        ?WorkType\Model $workType,
        IAssessable $assessable,
        bool $showClosed,
        Alaress_Date_Format $now,
        bool $expected
    ): void {
        // Apply participation
        $plan = (new Plan($assessable))->setIsDynamic(PlanMapper::PLAN_TYPE_DYNAMIC);
        if ($isParticipant) {
            $resultCacheMapper = ResultCache\Mapper::getInstance();

            $plan->getParticipants()[$user->getId()] = new Participant($user, $plan, $resultCacheMapper);
        }
        $assessable->setPlans($assessable->getPlans()->emptyClone()->add($plan));

        // Apply role
        $user->setRole($role);

        // Apply workType
        $assessable->setWorkType($workType);

        $visibility = new Visibility($this->getMockAccess());
        $this->assertEquals($expected, $visibility->isVisibleToUser($assessable, $user, $showClosed, $now));
    }


    /**
     * @return array
     * @throws InvalidArgumentException
     */
    public static function providerIsVisible(): array
    {
        // Is participant
        $isParticipant  = true;
        $notParticipant = false;

        // isStaff dimensions
        [$studentRole, $staffRole] = self::buildRoles();

        // isInstructor dimension
        [$nonInstructor, $isInstructor, $isLearner] = self::buildUsers();

        // workType dimension
        // The four visibility types are defined in WorkType\Mapper.
        // They are all possible combinations of two booleans (is_visible_to_parents, is_visible_to_learners).
        $workTypeNone = null;
        $workTypeSI   = (new WorkType\Model())->setIsVisibleToParents(false)->setIsVisibleToLearners(false);
        $workTypeSIP  = (new WorkType\Model())->setIsVisibleToParents(true)->setIsVisibleToLearners(false);
        $workTypeSIL  = (new WorkType\Model())->setIsVisibleToParents(false)->setIsVisibleToLearners(true);
        $workTypeSILP = (new WorkType\Model())->setIsVisibleToParents(true)->setIsVisibleToLearners(true);

        // Assessment temporal state
        $now = new Alaress_Date_Format(self::NOW_DATABASE, Alaress_Date_Format::DATABASE_FORMAT);
        [$unpublished, $upcoming, $open, $overdue, $closed] = self::buildAssessments($now);

        // Show closed flag
        $showClosed = true;
        $hideClosed = false;

        // Exhaustive combination test.
        return [
            // --- Participating student non-instructor ---

            // Participating student non-instructor, work type: None.
            // I see if it's published, unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNone, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNone, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNone, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNone, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNone, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNone, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNone, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNone, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNone, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNone, $closed, $hideClosed, $now, false],

            // Participating student non-instructor, work type: Staff-Instructors-Learners-Parents.
            // I see if it's published, unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSILP, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSILP, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSILP, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSILP, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSILP, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSILP, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSILP, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSILP, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSILP, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSILP, $closed, $hideClosed, $now, false],

//            // Participating student non-instructor, work type: Staff-Instructors-Parents.
//            // TODO: Figure out how to handle this case.  Student is not a learner and not an instructor.
//            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIP, $unpublished, $showClosed, $now, ],
//            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIP, $unpublished, $hideClosed, $now, ],
//            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIP, $upcoming, $showClosed, $now, ],
//            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIP, $upcoming, $hideClosed, $now, ],
//            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIP, $open, $showClosed, $now, ],
//            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIP, $open, $hideClosed, $now, ],
//            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIP, $overdue, $showClosed, $now, ],
//            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIP, $overdue, $hideClosed, $now, ],
//            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIP, $closed, $showClosed, $now, ],
//            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIP, $closed, $hideClosed, $now, ],

            // Participating student non-instructor, work type: Staff-Instructors-Learners.
            // I see if it's published, unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIL, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIL, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIL, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIL, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIL, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIL, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIL, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIL, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIL, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Participating student non-instructor, work type: Staff-Instructors.
            // I never see it
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSI, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSI, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSI, $upcoming, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSI, $upcoming, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSI, $open, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSI, $open, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSI, $overdue, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSI, $overdue, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSI, $closed, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSI, $closed, $hideClosed, $now, false],

            // --- Participating student instructor ---

            // Participating student instructor, work type: None.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $isInstructor, $workTypeNone, $unpublished, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNone, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNone, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNone, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNone, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNone, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNone, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNone, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNone, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNone, $closed, $hideClosed, $now, false],

            // Participating student instructor, work type: Staff-Instructors-Learners-Parents.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $isInstructor, $workTypeSILP, $unpublished, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSILP, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSILP, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSILP, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSILP, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSILP, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSILP, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSILP, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSILP, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Participating student instructor, work type: Staff-Instructors-Parents.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIP, $unpublished, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIP, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIP, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIP, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIP, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIP, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIP, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIP, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIP, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Participating student instructor, work type: Staff-Instructors-Learners.
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIL, $unpublished, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIL, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIL, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIL, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIL, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIL, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIL, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIL, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIL, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Participating student instructor, SIVO work type: Staff-Instructors.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $isInstructor, $workTypeSI, $unpublished, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSI, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSI, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSI, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSI, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSI, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSI, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSI, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSI, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSI, $closed, $hideClosed, $now, false],

            // --- Participating staff non-instructor ---

            // Participating staff non-instructor, work type: None.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNone, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNone, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNone, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNone, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNone, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNone, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNone, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNone, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNone, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNone, $closed, $hideClosed, $now, false],

            // Participating staff non-instructor, work type: Staff-Instructors-Learners-Parents.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSILP, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSILP, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSILP, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSILP, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSILP, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSILP, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSILP, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSILP, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSILP, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Participating staff non-instructor, work type: Staff-Instructors-Parents.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIP, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIP, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIP, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIP, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIP, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIP, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIP, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIP, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIP, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Participating staff non-instructor, work type: Staff-Instructors-Learners.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIL, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIL, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIL, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIL, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIL, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIL, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIL, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIL, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIL, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Participating staff non-instructor, work type: Staff-Instructors.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSI, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSI, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSI, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSI, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSI, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSI, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSI, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSI, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSI, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSI, $closed, $hideClosed, $now, false],

            // --- Participating staff instructor ---

            // Participating staff instructor, work type: None.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $isInstructor, $workTypeNone, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNone, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNone, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNone, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNone, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNone, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNone, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNone, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNone, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNone, $closed, $hideClosed, $now, false],

            // Participating staff instructor, work type: Staff-Instructors-Learners-Parents.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $isInstructor, $workTypeSILP, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSILP, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSILP, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSILP, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSILP, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSILP, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSILP, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSILP, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSILP, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Participating staff instructor, work type: Staff-Instructors-Parents.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIP, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIP, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIP, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIP, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIP, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIP, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIP, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIP, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIP, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Participating staff instructor, work type: Staff-Instructors-Learners.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIL, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIL, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIL, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIL, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIL, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIL, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIL, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIL, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIL, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Participating staff instructor, work type: Staff-Instructors.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $isInstructor, $workTypeSI, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSI, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSI, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSI, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSI, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSI, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSI, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSI, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSI, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSI, $closed, $hideClosed, $now, false],

            // --- Non-participating student non-instructor ---

            // Non-participating student non-instructor, work type: None.
            // I never see it
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNone, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNone, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNone, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNone, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNone, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNone, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNone, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNone, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNone, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNone, $closed, $hideClosed, $now, false],

            // Non-participating student non-instructor, work type: Staff-Instructors-Learners-Parents.
            // I never see it
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSILP, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSILP, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSILP, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSILP, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSILP, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSILP, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSILP, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSILP, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSILP, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Non-participating student non-instructor, work type: Staff-Instructors-Parents.
            // I never see it
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIP, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIP, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIP, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIP, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIP, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIP, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIP, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIP, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIP, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Non-participating student non-instructor, work type: Staff-Instructors-Learners.
            // I never see it
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIL, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIL, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIL, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIL, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIL, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIL, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIL, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIL, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIL, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Non-participating student non-instructor, work type: Staff-Instructors.
            // I never see it
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSI, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSI, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSI, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSI, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSI, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSI, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSI, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSI, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSI, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSI, $closed, $hideClosed, $now, false],

            // --- Non-participating student instructor ---

            // Non-participating student instructor, work type: None.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $studentRole, $isInstructor, $workTypeNone, $unpublished, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNone, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNone, $upcoming, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNone, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNone, $open, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNone, $open, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNone, $overdue, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNone, $overdue, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNone, $closed, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNone, $closed, $hideClosed, $now, false],

            // Non-participating student instructor, work type: Staff-Instructors-Learners-Parents.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $studentRole, $isInstructor, $workTypeSILP, $unpublished, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSILP, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSILP, $upcoming, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSILP, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSILP, $open, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSILP, $open, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSILP, $overdue, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSILP, $overdue, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSILP, $closed, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Non-participating student instructor, work type: Staff-Instructors-Parents.
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIP, $unpublished, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIP, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIP, $upcoming, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIP, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIP, $open, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIP, $open, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIP, $overdue, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIP, $overdue, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIP, $closed, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Non-participating student instructor, work type: Staff-Instructors-Learners.
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIL, $unpublished, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIL, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIL, $upcoming, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIL, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIL, $open, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIL, $open, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIL, $overdue, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIL, $overdue, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIL, $closed, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Non-participating student instructor, work type: Staff-Instructors.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $studentRole, $isInstructor, $workTypeSI, $unpublished, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSI, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSI, $upcoming, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSI, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSI, $open, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSI, $open, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSI, $overdue, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSI, $overdue, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSI, $closed, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSI, $closed, $hideClosed, $now, false],

            // --- Non-participating staff non-instructor ---

            // Non-participating staff non-instructor, work type: None.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNone, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNone, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNone, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNone, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNone, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNone, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNone, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNone, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNone, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNone, $closed, $hideClosed, $now, false],

            // Non-participating staff non-instructor, work type: Staff-Instructors-Learners-Parents.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSILP, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSILP, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSILP, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSILP, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSILP, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSILP, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSILP, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSILP, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSILP, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Non-participating staff non-instructor, work type: Staff-Instructors-Parents.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIP, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIP, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIP, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIP, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIP, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIP, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIP, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIP, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIP, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Non-participating staff non-instructor, work type: Staff-Instructors-Learners.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIL, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIL, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIL, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIL, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIL, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIL, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIL, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIL, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIL, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Non-participating staff non-instructor, work type: Staff-Instructors.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSI, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSI, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSI, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSI, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSI, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSI, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSI, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSI, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSI, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSI, $closed, $hideClosed, $now, false],

            // --- Non-participating staff instructor ---

            // Non-participating staff instructor, work type: None.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $isInstructor, $workTypeNone, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNone, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNone, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNone, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNone, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNone, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNone, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNone, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNone, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNone, $closed, $hideClosed, $now, false],

            // Non-participating staff instructor, work type: Staff-Instructors-Learners-Parents.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $isInstructor, $workTypeSILP, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSILP, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSILP, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSILP, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSILP, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSILP, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSILP, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSILP, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSILP, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Non-participating staff instructor, work type: Staff-Instructors-Parents.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIP, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIP, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIP, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIP, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIP, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIP, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIP, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIP, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIP, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Non-participating staff instructor, work type: Staff-Instructors-Learners.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIL, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIL, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIL, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIL, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIL, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIL, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIL, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIL, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIL, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Non-participating staff instructor, work type: Staff-Instructors.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $isInstructor, $workTypeSI, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSI, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSI, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSI, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSI, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSI, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSI, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSI, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSI, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSI, $closed, $hideClosed, $now, false],
        ];
    }


    /**
     * Returns the relevant different values for the isStaff dimension
     *
     * @return Role[]
     */
    private static function buildRoles(): array
    {
        return [
            (new Role())->setType(Role::STUDENT),
            (new Role())->setType(Role::STAFF),
        ];
    }


    /**
     * Returns the relevant different values for the isInstructor dimension
     *
     * @return User[]
     * @see $this->getMockAccess()
     */
    private static function buildUsers(): array
    {
        return [
            (new User())->setId(1)->setFirstname('Andy - non-instructor'),
            (new User())->setId(2)->setFirstname('Bart - is-instructor'),
            (new User())->setId(3)->setFirstname('Cath - is-learner'),
        ];
    }


    /**
     * Returns the relevant different values for the temporal state dimension
     *
     * @param Alaress_Date_Format $now
     * @return IAssessable[]
     */
    private static function buildAssessments(Alaress_Date_Format $now): array
    {

        $folderContext = (new Folder\Model())->setId(1);

        $unpublished = (new DueWork\Model())
            ->setId(1)
            ->setTitle('A DueWork - unpublished')
            ->setContext($folderContext)
            ->setAcceptsMultiple()// need to do this to avoid due/close date magic in dueWork::getCloseAt()
            ->setPublishAt((clone $now)->addDays(1))
            ->setOpenAt((clone $now)->addDays(2))
            ->setDueAt((clone $now)->addDays(3))
            ->setCloseAt((clone $now)->addDays(4));

        $upcoming = (new DueWork\Model())
            ->setId(2)
            ->setTitle('A DueWork - upcoming')
            ->setContext($folderContext)
            ->setAcceptsMultiple()
            ->setPublishAt((clone $now)->addDays(-1))
            ->setOpenAt((clone $now)->addDays(1))
            ->setDueAt((clone $now)->addDays(2))
            ->setCloseAt((clone $now)->addDays(3));

        $open = (new DueWork\Model())
            ->setId(3)
            ->setTitle('A DueWork - open')
            ->setContext($folderContext)
            ->setAcceptsMultiple()
            ->setPublishAt((clone $now)->addDays(-2))
            ->setOpenAt((clone $now)->addDays(-1))
            ->setDueAt((clone $now)->addDays(1))
            ->setCloseAt((clone $now)->addDays(2));

        $overdue = (new DueWork\Model())
            ->setId(4)
            ->setTitle('A DueWork - overdue')
            ->setContext($folderContext)
            ->setAcceptsMultiple()
            ->setPublishAt((clone $now)->addDays(-3))
            ->setOpenAt((clone $now)->addDays(-2))
            ->setDueAt((clone $now)->addDays(-1))
            ->setCloseAt((clone $now)->addDays(1));

        $closed = (new DueWork\Model())
            ->setId(5)
            ->setTitle('A DueWork - closed')
            ->setContext($folderContext)
            ->setAcceptsMultiple()
            ->setPublishAt((clone $now)->addDays(-4))
            ->setOpenAt((clone $now)->addDays(-3))
            ->setDueAt((clone $now)->addDays(-2))
            ->setCloseAt((clone $now)->addDays(-1));

        return [$unpublished, $upcoming, $open, $overdue, $closed];
    }


    /**
     * Builds a mock of the Access dependency
     *
     * @return Access|MockObject
     * @throws PHPUnit\Framework\Exception
     * @throws RuntimeException
     * @see self::buildUsers()
     */
    private function getMockAccess(): Access|MockObject
    {
        /** @var Access|MockObject $mockAccess */
        $mockAccess = $this->createMock(Access::class);
        $mockAccess
            ->expects($this->any())
            ->method('getFolderAccess')
            ->willReturnCallback(function (User $user) {
                return match ($user->getId()) {
                    1 => UserAccess::ACCESS_DENY,
                    2 => UserAccess::ACCESS_WRITE,
                    3 => UserAccess::ACCESS_READ,
                    default => throw new \InvalidArgumentException("No access level defined for user '{$user->getId()}'"),
                };
            });
        return $mockAccess;
    }
}
