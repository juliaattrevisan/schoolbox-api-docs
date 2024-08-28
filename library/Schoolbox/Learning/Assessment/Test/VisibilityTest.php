<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Test;

use PHPUnit\Framework\TestCase;
use Alaress_Date_Format;
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
 * 4. workType:      [none, non-SIVO, SIVO]
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
        $plan = (new Plan($assessable))->setIsDynamic(true);
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
        [$nonInstructor, $isInstructor] = self::buildUsers();

        // workType dimension
        $workTypeNone   = null;
        $workTypeNormal = (new WorkType\Model())->setStaffOrInstructorVisibleOnly(false);
        $workTypeSivo   = (new WorkType\Model())->setStaffOrInstructorVisibleOnly(true);

        // Assessment temporal state
        $now = new Alaress_Date_Format(self::NOW_DATABASE, Alaress_Date_Format::DATABASE_FORMAT);
        [$unpublished, $upcoming, $open, $overdue, $closed] = self::buildAssessments($now);

        // Show closed flag
        $showClosed = true;
        $hideClosed = false;

        // Exhaustive combination test.
        return [
            // Participating student non-instructor, no work type:
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

            // Participating student non-instructor, non-SIVO work type:
            // I see if it's published, unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNormal, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNormal, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNormal, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNormal, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNormal, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNormal, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNormal, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNormal, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNormal, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeNormal, $closed, $hideClosed, $now, false],

            // Participating student non-instructor, SIVO work type:
            // I never see it
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSivo, $unpublished, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSivo, $unpublished, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSivo, $upcoming, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSivo, $upcoming, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSivo, $open, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSivo, $open, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSivo, $overdue, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSivo, $overdue, $hideClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSivo, $closed, $showClosed, $now, false],
            [$isParticipant, $studentRole, $nonInstructor, $workTypeSivo, $closed, $hideClosed, $now, false],

            // Participating student instructor, no work type:
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

            // Participating student instructor, non-SIVO work type:
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $isInstructor, $workTypeNormal, $unpublished, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNormal, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNormal, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNormal, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNormal, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNormal, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNormal, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNormal, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNormal, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeNormal, $closed, $hideClosed, $now, false],

            // Participating student instructor, SIVO work type:
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $studentRole, $isInstructor, $workTypeSivo, $unpublished, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSivo, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSivo, $upcoming, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSivo, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSivo, $open, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSivo, $open, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSivo, $overdue, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSivo, $overdue, $hideClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSivo, $closed, $showClosed, $now, true],
            [$isParticipant, $studentRole, $isInstructor, $workTypeSivo, $closed, $hideClosed, $now, false],

            // Participating staff non-instructor, no work type:
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

            // Participating staff non-instructor, non-SIVO work type:
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNormal, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNormal, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNormal, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNormal, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNormal, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNormal, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNormal, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNormal, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNormal, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeNormal, $closed, $hideClosed, $now, false],

            // Participating staff non-instructor, SIVO work type:
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSivo, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSivo, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSivo, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSivo, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSivo, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSivo, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSivo, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSivo, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSivo, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $nonInstructor, $workTypeSivo, $closed, $hideClosed, $now, false],

            // Participating staff instructor, no work type:
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

            // Participating staff instructor, non-SIVO work type:
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $isInstructor, $workTypeNormal, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNormal, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNormal, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNormal, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNormal, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNormal, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNormal, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNormal, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNormal, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeNormal, $closed, $hideClosed, $now, false],

            // Participating staff instructor, SIVO work type:
            // I see it unless it's closed and hideClosed is on
            [$isParticipant, $staffRole, $isInstructor, $workTypeSivo, $unpublished, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSivo, $unpublished, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSivo, $upcoming, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSivo, $upcoming, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSivo, $open, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSivo, $open, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSivo, $overdue, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSivo, $overdue, $hideClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSivo, $closed, $showClosed, $now, true],
            [$isParticipant, $staffRole, $isInstructor, $workTypeSivo, $closed, $hideClosed, $now, false],

            // Non-participating student non-instructor, no work type:
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

            // Non-participating student non-instructor, non-SIVO work type:
            // I never see it
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNormal, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNormal, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNormal, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNormal, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNormal, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNormal, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNormal, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNormal, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNormal, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeNormal, $closed, $hideClosed, $now, false],

            // Non-participating student non-instructor, SIVO work type:
            // I never see it
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSivo, $unpublished, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSivo, $unpublished, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSivo, $upcoming, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSivo, $upcoming, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSivo, $open, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSivo, $open, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSivo, $overdue, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSivo, $overdue, $hideClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSivo, $closed, $showClosed, $now, false],
            [$notParticipant, $studentRole, $nonInstructor, $workTypeSivo, $closed, $hideClosed, $now, false],

            // Non-participating student instructor, no work type:
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

            // Non-participating student instructor, non-SIVO work type:
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $studentRole, $isInstructor, $workTypeNormal, $unpublished, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNormal, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNormal, $upcoming, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNormal, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNormal, $open, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNormal, $open, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNormal, $overdue, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNormal, $overdue, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNormal, $closed, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeNormal, $closed, $hideClosed, $now, false],

            // Non-participating student instructor, SIVO work type:
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $studentRole, $isInstructor, $workTypeSivo, $unpublished, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSivo, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSivo, $upcoming, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSivo, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSivo, $open, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSivo, $open, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSivo, $overdue, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSivo, $overdue, $hideClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSivo, $closed, $showClosed, $now, true],
            [$notParticipant, $studentRole, $isInstructor, $workTypeSivo, $closed, $hideClosed, $now, false],

            // Non-participating staff non-instructor, no work type:
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

            // Non-participating staff non-instructor, non-SIVO work type:
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNormal, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNormal, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNormal, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNormal, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNormal, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNormal, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNormal, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNormal, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNormal, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeNormal, $closed, $hideClosed, $now, false],

            // Non-participating staff non-instructor, SIVO work type:
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSivo, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSivo, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSivo, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSivo, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSivo, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSivo, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSivo, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSivo, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSivo, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $nonInstructor, $workTypeSivo, $closed, $hideClosed, $now, false],

            // Non-participating staff instructor, no work type:
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

            // Non-participating staff instructor, non-SIVO work type:
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $isInstructor, $workTypeNormal, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNormal, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNormal, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNormal, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNormal, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNormal, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNormal, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNormal, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNormal, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeNormal, $closed, $hideClosed, $now, false],

            // Non-participating staff instructor, SIVO work type:
            // I see it unless it's closed and hideClosed is on
            [$notParticipant, $staffRole, $isInstructor, $workTypeSivo, $unpublished, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSivo, $unpublished, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSivo, $upcoming, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSivo, $upcoming, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSivo, $open, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSivo, $open, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSivo, $overdue, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSivo, $overdue, $hideClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSivo, $closed, $showClosed, $now, true],
            [$notParticipant, $staffRole, $isInstructor, $workTypeSivo, $closed, $hideClosed, $now, false],
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
                switch ($user->getId()) {
                    case 1:
                        return UserAccess::ACCESS_DENY;
                    case 2:
                        return UserAccess::ACCESS_WRITE;
                }
                throw new \InvalidArgumentException("No access level defined for user '{$user->getId()}'");
            });
        return $mockAccess;
    }
}
