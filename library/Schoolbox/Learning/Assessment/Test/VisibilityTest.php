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
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\Learning\Assessment\Project\ResultCache;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Role;
use Schoolbox\User;
use InvalidArgumentException;
use PHPUnit;
use PHPUnit\Framework\MockObject\Stub;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Resource\Folder;

/**
 * This class tests whether a user can see an assessment by considering the following factors:
 * 1. isParticipant:       [true, false]
 * 2. isStaff:             [true, false]
 * 3. folderAccess:        [DENY, READ, WRITE]
 * 4. workType visibility: [none, staff-instructors-learners-parents, staff-instructors-parents, staff-instructors-learners, staff-instructors]
 * 5. showClosed:          [true, false]
 * 6. state:               [unpublished, upcoming, open, overdue, closed]
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

        $visibility = new Visibility($this->getAccessStub());
        $this->assertEquals($expected, $visibility->isVisibleToUser($assessable, $user, $showClosed, $now));
    }


    /**
     * @return array
     * @throws InvalidArgumentException
     */
    public static function providerIsVisible(): array
    {
        // Participant dimension
        $isParticipant  = true;
        $notParticipant = false;

        // Role dimension
        [$studentRole, $staffRole] = self::buildRoles();

        // Instructor/Learner/Parent-of-learner dimension
        [$nonInstructorNonLearner, $isInstructor, $isLearner, $isParentOfLearner] = self::buildUsers();

        // WorkType dimension
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
            // --- Participating student non-instructor-non-learner ---

            // Participating student non-instructor-non-learner, work type: None.
            // I see if it's published, unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $closed, $hideClosed, $now, false],

            // Participating student non-instructor-non-learner, work type: Staff-Instructors-Learners-Parents.
            // I see if it's published, unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Participating student non-instructor-non-learner, work type: Staff-Instructors-Parents.
            // I see if it's published, unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Participating student non-instructor-non-learner, work type: Staff-Instructors-Learners.
            // I see if it's published, unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Participating student non-instructor-non-learner, work type: Staff-Instructors.
            // I never see it
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $upcoming, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $upcoming, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $open, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $open, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $overdue, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $overdue, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $closed, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $closed, $hideClosed, $now, false],

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
            // I see it unless it's closed and hideClosed is on
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

            // Participating student instructor, work type: Staff-Instructors.
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

            // --- Participating student learner ---

            // Participating student learner, work type: None.
            // I see if it's published, unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $isLearner, $workTypeNone, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeNone, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeNone, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeNone, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeNone, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeNone, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeNone, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeNone, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeNone, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeNone, $closed, $hideClosed, $now, false],

            // Participating student learner, work type: Staff-Instructors-Learners-Parents.
            // I see if it's published, unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $isLearner, $workTypeSILP, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSILP, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSILP, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeSILP, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeSILP, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeSILP, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeSILP, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeSILP, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeSILP, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Participating student learner, work type: Staff-Instructors-Parents.
            // I never see it (work type excludes learners)
            [$isParticipant, $studentRole, $isLearner, $workTypeSIP, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIP, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIP, $upcoming, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIP, $upcoming, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIP, $open, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIP, $open, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIP, $overdue, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIP, $overdue, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIP, $closed, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Participating student learner, work type: Staff-Instructors-Learners.
            // I see if it's published, unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $isLearner, $workTypeSIL, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIL, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIL, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIL, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIL, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIL, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIL, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIL, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIL, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isLearner, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Participating student learner, work type: Staff-Instructors.
            // I never see it (work type excludes learners)
            [$isParticipant, $studentRole, $isLearner, $workTypeSI, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSI, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSI, $upcoming, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSI, $upcoming, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSI, $open, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSI, $open, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSI, $overdue, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSI, $overdue, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSI, $closed, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isLearner, $workTypeSI, $closed, $hideClosed, $now, false],

            // --- Participating student parent-of-learner ---

            // Participating student parent-of-learner, work type: None.
            // I see if it's published, unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $closed, $hideClosed, $now, false],

            // Participating student parent-of-learner, work type: Staff-Instructors-Learners-Parents.
            // I never see it in this fixture, because parent visibility requires a child learner participant
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $upcoming, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $upcoming, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $open, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $open, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $overdue, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $overdue, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $closed, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Participating student parent-of-learner, work type: Staff-Instructors-Parents.
            // I never see it in this fixture, because parent visibility requires a child learner participant
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $upcoming, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $upcoming, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $open, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $open, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $overdue, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $overdue, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $closed, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Participating student parent-of-learner, work type: Staff-Instructors-Learners.
            // I see if it's published as a participant (parent path not taken: not visible to parents), unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Participating student parent-of-learner, work type: Staff-Instructors.
            // I never see it (work type restricts to staff and instructors only)
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $upcoming, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $upcoming, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $open, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $open, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $overdue, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $overdue, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $closed, $showClosed, $now, false],
            [$isParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $closed, $hideClosed, $now, false],

            // --- Participating staff non-instructor-non-learner ---

            // Participating staff non-instructor-non-learner, work type: None.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $closed, $hideClosed, $now, false],

            // Participating staff non-instructor-non-learner, work type: Staff-Instructors-Learners-Parents.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Participating staff non-instructor-non-learner, work type: Staff-Instructors-Parents.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Participating staff non-instructor-non-learner, work type: Staff-Instructors-Learners.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Participating staff non-instructor-non-learner, work type: Staff-Instructors.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $closed, $hideClosed, $now, false],

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

            // --- Participating staff learner ---

            // Participating staff learner, work type: None.
            // I see if it's published, unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $isLearner, $workTypeNone, $unpublished, $showClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeNone, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeNone, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeNone, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeNone, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeNone, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeNone, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeNone, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeNone, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeNone, $closed, $hideClosed, $now, false],

            // Participating staff learner, work type: Staff-Instructors-Learners-Parents.
            // I see if it's published, unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $isLearner, $workTypeSILP, $unpublished, $showClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSILP, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSILP, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeSILP, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeSILP, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeSILP, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeSILP, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeSILP, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeSILP, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Participating staff learner, work type: Staff-Instructors-Parents.
            // I never see it (work type excludes learners)
            [$isParticipant, $staffRole, $isLearner, $workTypeSIP, $unpublished, $showClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIP, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIP, $upcoming, $showClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIP, $upcoming, $hideClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIP, $open, $showClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIP, $open, $hideClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIP, $overdue, $showClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIP, $overdue, $hideClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIP, $closed, $showClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Participating staff learner, work type: Staff-Instructors-Learners.
            // I see if it's published, unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $isLearner, $workTypeSIL, $unpublished, $showClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIL, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIL, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIL, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIL, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIL, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIL, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIL, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIL, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isLearner, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Participating staff learner, work type: Staff-Instructors.
            // I never see it (work type excludes learners)
            [$isParticipant, $staffRole, $isLearner, $workTypeSI, $unpublished, $showClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSI, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSI, $upcoming, $showClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSI, $upcoming, $hideClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSI, $open, $showClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSI, $open, $hideClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSI, $overdue, $showClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSI, $overdue, $hideClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSI, $closed, $showClosed, $now, false],
            [$isParticipant, $staffRole, $isLearner, $workTypeSI, $closed, $hideClosed, $now, false],

            // --- Participating staff parent-of-learner ---
            // isLearner returns false for this user, so the learner path is never taken.
            // The staff check fires first, bypassing all work-type visibility restrictions.

            // Participating staff parent-of-learner, work type: None.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $closed, $hideClosed, $now, false],

            // Participating staff parent-of-learner, work type: Staff-Instructors-Learners-Parents.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Participating staff parent-of-learner, work type: Staff-Instructors-Parents.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Participating staff parent-of-learner, work type: Staff-Instructors-Learners.
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Participating staff parent-of-learner, work type: Staff-Instructors.
            // I see it unless it's closed and hideClosed is on (staff bypass ignores work-type restrictions)
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $closed, $hideClosed, $now, false],

            // --- Non-participating student non-instructor-non-learner ---

            // Non-participating student non-instructor-non-learner, work type: None.
            // I never see it
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeNone, $closed, $hideClosed, $now, false],

            // Non-participating student non-instructor-non-learner, work type: Staff-Instructors-Learners-Parents.
            // I never see it
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Non-participating student non-instructor-non-learner, work type: Staff-Instructors-Parents.
            // I never see it
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Non-participating student non-instructor-non-learner, work type: Staff-Instructors-Learners.
            // I never see it
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Non-participating student non-instructor-non-learner, work type: Staff-Instructors.
            // I never see it
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructorNonLearner, $workTypeSI, $closed, $hideClosed, $now, false],

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

            // --- Non-participating student learner ---

            // Non-participating student learner, work type: None.
            // I never see it (not a participant)
            [$notParticipant, $studentRole, $isLearner, $workTypeNone, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeNone, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeNone, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeNone, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeNone, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeNone, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeNone, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeNone, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeNone, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeNone, $closed, $hideClosed, $now, false],

            // Non-participating student learner, work type: Staff-Instructors-Learners-Parents.
            // I never see it (not a participant)
            [$notParticipant, $studentRole, $isLearner, $workTypeSILP, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSILP, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSILP, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSILP, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSILP, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSILP, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSILP, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSILP, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSILP, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Non-participating student learner, work type: Staff-Instructors-Parents.
            // I never see it (work type excludes learners)
            [$notParticipant, $studentRole, $isLearner, $workTypeSIP, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIP, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIP, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIP, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIP, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIP, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIP, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIP, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIP, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Non-participating student learner, work type: Staff-Instructors-Learners.
            // I never see it (not a participant)
            [$notParticipant, $studentRole, $isLearner, $workTypeSIL, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIL, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIL, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIL, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIL, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIL, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIL, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIL, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIL, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Non-participating student learner, work type: Staff-Instructors.
            // I never see it (work type excludes learners)
            [$notParticipant, $studentRole, $isLearner, $workTypeSI, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSI, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSI, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSI, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSI, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSI, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSI, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSI, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSI, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isLearner, $workTypeSI, $closed, $hideClosed, $now, false],

            // --- Non-participating student parent-of-learner ---

            // Non-participating student parent-of-learner, work type: None.
            // I never see it (not a participant, and no child learner access path when workType is null)
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeNone, $closed, $hideClosed, $now, false],

            // Non-participating student parent-of-learner, work type: Staff-Instructors-Learners-Parents.
            // I never see it in this fixture, because parent visibility requires a child learner participant
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Non-participating student parent-of-learner, work type: Staff-Instructors-Parents.
            // I never see it in this fixture, because parent visibility requires a child learner participant
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Non-participating student parent-of-learner, work type: Staff-Instructors-Learners.
            // I never see it (work type not visible to parents; not a participant)
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Non-participating student parent-of-learner, work type: Staff-Instructors.
            // I never see it (work type restricts to staff and instructors only)
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $isParentOfLearner, $workTypeSI, $closed, $hideClosed, $now, false],

            // --- Non-participating staff non-instructor-non-learner ---

            // Non-participating staff non-instructor-non-learner, work type: None.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeNone, $closed, $hideClosed, $now, false],

            // Non-participating staff non-instructor-non-learner, work type: Staff-Instructors-Learners-Parents.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Non-participating staff non-instructor-non-learner, work type: Staff-Instructors-Parents.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Non-participating staff non-instructor-non-learner, work type: Staff-Instructors-Learners.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Non-participating staff non-instructor-non-learner, work type: Staff-Instructors.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructorNonLearner, $workTypeSI, $closed, $hideClosed, $now, false],

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

            // --- Non-participating staff learner ---

            // Non-participating staff learner, work type: None.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $isLearner, $workTypeNone, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeNone, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeNone, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeNone, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeNone, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeNone, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeNone, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeNone, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeNone, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeNone, $closed, $hideClosed, $now, false],

            // Non-participating staff learner, work type: Staff-Instructors-Learners-Parents.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $isLearner, $workTypeSILP, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSILP, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSILP, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSILP, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSILP, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSILP, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSILP, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSILP, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSILP, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Non-participating staff learner, work type: Staff-Instructors-Parents.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $isLearner, $workTypeSIP, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIP, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIP, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIP, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIP, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIP, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIP, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIP, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIP, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Non-participating staff learner, work type: Staff-Instructors-Learners.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $isLearner, $workTypeSIL, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIL, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIL, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIL, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIL, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIL, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIL, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIL, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIL, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Non-participating staff learner, work type: Staff-Instructors.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $isLearner, $workTypeSI, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSI, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSI, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSI, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSI, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSI, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSI, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSI, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSI, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isLearner, $workTypeSI, $closed, $hideClosed, $now, false],

            // --- Non-participating staff parent-of-learner ---
            // isLearner returns false for this user, so the learner path is never taken.
            // The staff check fires first, bypassing all work-type visibility restrictions and the participation requirement.

            // Non-participating staff parent-of-learner, work type: None.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeNone, $closed, $hideClosed, $now, false],

            // Non-participating staff parent-of-learner, work type: Staff-Instructors-Learners-Parents.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSILP, $closed, $hideClosed, $now, false],

            // Non-participating staff parent-of-learner, work type: Staff-Instructors-Parents.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIP, $closed, $hideClosed, $now, false],

            // Non-participating staff parent-of-learner, work type: Staff-Instructors-Learners.
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSIL, $closed, $hideClosed, $now, false],

            // Non-participating staff parent-of-learner, work type: Staff-Instructors.
            // I see it unless it's closed and hideClosed is on (staff bypass ignores work-type restrictions)
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isParentOfLearner, $workTypeSI, $closed, $hideClosed, $now, false],
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
     * Note the access level of the returned users is determined by getMockAccess().
     *
     * @return User[]
     * @see $this->getMockAccess()
     */
    private static function buildUsers(): array
    {
        return [
            (new User())->setId(1)->setFirstname('Andy - non-instructor-non-learner'),
            (new User())->setId(2)->setFirstname('Bart - is-instructor'),
            (new User())->setId(3)->setFirstname('Cath - is-learner'),
            (new User())->setId(4)->setFirstname('Demi - is-parent-of-learner'),
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
     * Builds a stub of the Access dependency
     *
     * @return Access&Stub
     * @throws PHPUnit\Framework\Exception
     * @see self::buildUsers()
     */
    private function getAccessStub(): Access
    {
        /** @var Access&Stub $access */
        $access = $this->createStub(Access::class);
        $access
            ->method('isInstructor')
            ->willReturnCallback(function (User $user, Folder\Model $folder) {
                return match ($user->getId()) {
                    1 => false,
                    2 => true,
                    3 => false,
                    4 => false,
                    default => throw new \InvalidArgumentException("No access level defined for user '{$user->getId()}'"),
                };
            });
        $access
            ->method('isLearner')
            ->willReturnCallback(function (User $user, Folder\Model $folder) {
                return match ($user->getId()) {
                    1 => false,
                    2 => false,
                    3 => true,
                    4 => false,
                    default => throw new \InvalidArgumentException("No access level defined for user '{$user->getId()}'"),
                };
            });
        $access
            ->method('getChildLearnersOfParent')
            ->willReturnCallback(function (User $user, Folder\Model $folder) {
                if ($user->getId() !== 4) {
                    return new GenericCollection(User::class);
                }
                $users = $this->buildUsers();
                // TODO: Test case where multiple children are returned.
                return new GenericCollection(User::class, [$users[2]]); // Return a collection containing only learner user 'Cath'.
            });
        return $access;
    }
}
