<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral;

use Psr\Log\LoggerInterface;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccess;
use Schoolbox\Pastoral\Comment\Model as Comment;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Member\Model as PastoralGroupMember;
use Schoolbox\Pastoral\Group\Model as PastoralGroup;
use Schoolbox\Pastoral\Report\Model as Report;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Helper for building a list of users to target notifications for a pastoral
 * record or comment.
 */
class NotifierHelper
{
    /**
     * Constructor
     *
     * @param UserMapper          $userMapper
     * @param PastoralGroupMapper $pastoralGroupMapper
     * @param AccessLevelCensor   $accessLevelCensor
     * @param LoggerInterface     $log
     */
    public function __construct(
        private UserMapper $userMapper,
        private PastoralGroupMapper $pastoralGroupMapper,
        private AccessLevelCensor $accessLevelCensor,
        private LoggerInterface $log
    ) {
    }

    /**
     * @param Model        $pastoral
     * @param Comment|null $comment
     * @return string
     */
    public static function getRecordHref(Model $pastoral, ?Comment $comment): string
    {
        $recordHref = sprintf("/pastoral/student/%s/record/%s", $pastoral->getStudent()->getId(), $pastoral->getId());
        if ($comment) {
            $recordHref .= '#' . $comment->getPanel();
        }
        return $recordHref;
    }

    /**
     * @param Report        $report
     * @param Model|null    $pastoral
     * @return string
     */
    public static function getReportHref(Report $report, ?Model $pastoral): string
    {
        return sprintf("/pastoral/report/results/%d#record%d", $report->getId(), $pastoral->getId());
    }

    /**
     * Build the list of users to target.
     *
     * @param iterable<PastoralAccess> $accessRecords
     * @param bool                     $privateChangeOnly
     * @param User                     $student              The student for whom the record was created.
     *                                                       Expects that `loadParents` and `loadStudents`
     *                                                       have been called.
     * @param bool                     $parentAccessEnabled
     * @param bool                     $studentAccessEnabled
     * @param string                   $recordType
     * @param string                   $method
     * @return array<int, User>
     */
    public function buildTargetUsers(
        iterable $accessRecords,
        bool $privateChangeOnly,
        User $student,
        bool $parentAccessEnabled,
        bool $studentAccessEnabled,
        string $recordType,
        string $method,
    ): array {
        $targetUsers = [];

        /*
         * For Staff:
         * * If the method is xxxRelated, it should be delivered only to role based permissions.
         * * If the method is NOT xxxRelated, it should be delivered only to user or group based permissions.
         */
        $roleBased = in_array($method, [
            Notifier::TRIGGER_CREATED_RELATED,
            Notifier::TRIGGER_UPDATED_RELATED,
            \Schoolbox\Pastoral\Comment\Notifier::TRIGGER_CREATED_RELATED,
            \Schoolbox\Pastoral\Comment\Notifier::TRIGGER_UPDATED_RELATED,
        ]);

        foreach ($accessRecords as $pastoralAccess) {
            // If the access is denied, fail
            if ($pastoralAccess->getAccess() === PastoralAccessMapper::ACCESS_DENY) {
                // do not send any notification
                continue;
            }

            // if the access is public only and the changes are private only, fail
            if (($pastoralAccess->getAccess() === PastoralAccessMapper::ACCESS_PUBLIC) && $privateChangeOnly) {
                // do not send any notification
                continue;
            }

            // If recordType is "pastoral comment" and comment is disabled, add this to parent/student access enabled flag
            $nonStaffCommentAllowed = $recordType !== 'pastoral comment' || $pastoralAccess->getCommentsAllowed();

            // Build the list of target users
            try {
                if (
                    $pastoralAccess->getRole() instanceof Role
                    && ($roleBased || !$pastoralAccess->getRole()->isStaff())
                ) {
                    // Role based message or role is not for staff
                    $targetUsers += $this->buildTargetUsersForRole(
                        $pastoralAccess->getRole(),
                        $student,
                        $parentAccessEnabled && $nonStaffCommentAllowed,
                        $studentAccessEnabled && $nonStaffCommentAllowed,
                        $pastoralAccess->getMatchCampus()
                    );
                } elseif (
                    $pastoralAccess->getRoleType()
                    && ($roleBased || $pastoralAccess->getRoleType() !== Role::STAFF)
                ) {
                    $targetUsers += $this->buildTargetUsersForRoleType(
                        $pastoralAccess->getRoleType(),
                        $student,
                        $parentAccessEnabled && $nonStaffCommentAllowed,
                        $studentAccessEnabled && $nonStaffCommentAllowed,
                        $pastoralAccess->getMatchCampus()
                    );
                } elseif (!$roleBased && $pastoralAccess->getGroup() instanceof PastoralGroup) {
                    $targetUsers += $this->buildTargetUsersForGroup(
                        $pastoralAccess->getGroup()->getId(),
                        $student,
                        $parentAccessEnabled && $nonStaffCommentAllowed,
                        $studentAccessEnabled && $nonStaffCommentAllowed,
                        $pastoralAccess->getMatchCampus(),
                    );
                } elseif (!$roleBased && $pastoralAccess->getUser() instanceof User) {
                    $user        = $this->userMapper->findById($pastoralAccess->getUser()->getId());
                    $targetUsers += $this->buildTargetUsersForUser(
                        $user,
                        $parentAccessEnabled && $nonStaffCommentAllowed,
                        $studentAccessEnabled && $nonStaffCommentAllowed,
                    );
                }
            } catch (MapperException $e) {
                // Log an error and move on so we don't break the rest of the notifications
                $this->log->error("Error: Unable to send $recordType $method notification. {$e->getMessage()}", ['exception' => $e]);
            }
        }

        return $targetUsers;
    }

    /**
     * Build the list of target users for a given role.
     *
     * @param Role $role
     * @param User $student              Only the student's teachers and parents
     *                                   will be notified.
     * @param bool $parentAccessEnabled
     * @param bool $studentAccessEnabled
     * @param bool $matchCampus
     * @return array
     */
    private function buildTargetUsersForRole(
        Role $role,
        User $student,
        bool $parentAccessEnabled,
        bool $studentAccessEnabled,
        bool $matchCampus
    ): array {
        $targetUsers = [];
        if ($role->isStaff() && $role->getAccessPastoral()) {
            // Only notify staff if they are a teacher of the student
            foreach ($student->getTeacherCollection() as $teacher) {
                if (
                    $teacher->getRole()->getId() === $role->getId()
                    && (!$matchCampus || AccessLevelCensor::inSameCampus($student, $teacher))
                ) {
                    $targetUsers[$teacher->getId()] = $teacher;
                }
            }
        } elseif ($role->isParent() && $parentAccessEnabled) {
            // Only send to parents if pastoral parent access is enabled
            foreach ($student->getParents() as $parent) {
                if ($parent->getRole()->getId() === $role->getId()) {
                    $targetUsers[$parent->getId()] = $parent;
                }
            }
        } elseif (
            $role->isStudent()
            && $studentAccessEnabled
            && $student->getRole()->getId() === $role->getId()
        ) {
            // Only send to student if student access is enabled
            // (Setting student access by role is not currently
            //  supported in frontend: only by role type)
            $targetUsers[$student->getId()] = $student;
        }
        return $targetUsers;
    }

    /**
     * Build the list of target users for a given role type.
     *
     * @param string $roleType
     * @param User   $student              Only the student's teachers and
     *                                     parents will be notified.
     * @param bool   $parentAccessEnabled
     * @param bool   $studentAccessEnabled
     * @param bool   $matchCampus
     * @return array
     */
    private function buildTargetUsersForRoleType(
        string $roleType,
        User $student,
        bool $parentAccessEnabled,
        bool $studentAccessEnabled,
        bool $matchCampus
    ): array {
        // The system should only notify staff if they are a teacher of the student
        $targetUsers = [];
        switch ($roleType) {
            case Role::STAFF:
                // Only notify student's teachers,
                // if their role allows it
                foreach ($student->getTeacherCollection() as $teacher) {
                    if (
                        $teacher->getRole()->getAccessPastoral()
                        && (!$matchCampus || AccessLevelCensor::inSameCampus($student, $teacher))
                    ) {
                        $targetUsers[$teacher->getId()] = $teacher;
                    }
                }
                break;

            case Role::STUDENT:
                // Only notify student if student access enabled
                if ($studentAccessEnabled) {
                    $targetUsers[$student->getId()] = $student;
                }
                break;

            case Role::PARENT:
                // Only notify parents if parent access enabled
                if ($parentAccessEnabled) {
                    foreach ($student->getParents() as $parent) {
                        $targetUsers[$parent->getId()] = $parent;
                    }
                }
                break;

            default:
                break;
        }
        return $targetUsers;
    }

    /**
     * Build the list of target users for a given pastoral group.
     *
     * @param int  $groupId
     * @param User $student
     * @param bool $parentAccessEnabled
     * @param bool $studentAccessEnabled
     * @param bool $matchCampus
     * @return array
     * @throws MapperException if the pastoral group doesn't exist
     * @throws Deleted
     */
    private function buildTargetUsersForGroup(
        int $groupId,
        User $student,
        bool $parentAccessEnabled,
        bool $studentAccessEnabled,
        bool $matchCampus
    ): array {
        // Sending to a pastoral group, make sure its hydrated
        $pastoralGroup = $this->pastoralGroupMapper->findById($groupId);
        $targetUsers   = [];

        /** @var PastoralGroupMember $member */
        foreach ($pastoralGroup->getMembersWithPermission() as $member) {
            $memberUser = $this->userMapper->findById($member->getUser()->getId());
            if ($matchCampus && !AccessLevelCensor::inSameCampus($student, $memberUser)) {
                $this->log->debug("Group Member {$member->getUser()->getId()} is not in campus");
                continue;
            }
            // Only staff should be in pastoral groups,
            // but handle students and parents in case they are there
            $targetUsers += $this->buildTargetUsersForUser(
                $memberUser,
                $parentAccessEnabled,
                $studentAccessEnabled,
            );
        }
        return $targetUsers;
    }

    /**
     * Build the list of target users for a given user.
     *
     * @param User $user
     * @param bool $parentAccessEnabled
     * @param bool $studentAccessEnabled
     * @return array
     */
    private function buildTargetUsersForUser(
        User $user,
        bool $parentAccessEnabled,
        bool $studentAccessEnabled
    ): array {
        // Sending to a user, make sure its hydrated
        $targetUsers = [];

        // Only send to parents if parent access is enabled,
        // or to students if student access is enabled
        if (
            ($user->isStaffWithPastoralAccess())
            || ($user->getRole()->isParent() && $parentAccessEnabled)
            || ($user->getRole()->isStudent() && $studentAccessEnabled)
        ) {
            $targetUsers[$user->getId()] = $user;
        }
        return $targetUsers;
    }

    /**
     * @param Model    $pastoral
     * @param Report   $report
     * @param int<1,2> $accessLevel least required level
     * @return GenericCollection
     */
    public function findUsersForRecordAndReport(Model $pastoral, Report $report, int $accessLevel): GenericCollection
    {
        $targetUsers = $this->userMapper->createCollection();
        // target users are the report owner and report members
        $reportUserIds   = $report->getUserIds(\Schoolbox\Pastoral\Report\Access\Model::READ);
        $reportUserIds[] = $report->getOwner()->getId();
        // check access for this pastoral record for each of the users, and add to targetUsers
        foreach ($reportUserIds as $reportUserId) {
            // if already exists, continue
            if ($targetUsers->offsetExists($reportUserId)) {
                continue;
            }
            // if owner of the record, add the user to target
            if ($pastoral->getOwner()->getId() === $reportUserId) {
                $targetUsers->offsetSet($pastoral->getOwner()->getId(), $pastoral->getOwner());
                continue;
            }
            try {
                $reportUser = $this->userMapper->findById($reportUserId);
            } catch (MapperException) {
                continue;
            }
            // if the user is moderator and the report is not limited to involved, add the user
            if ($this->accessLevelCensor->isPastoralModerator($reportUser) && !$report->getInvolved()) {
                $targetUsers->offsetSet($reportUser->getId(), $reportUser);
                continue;
            }
            // check role and permission
            if (!$reportUser->getRole()->getAccessPastoral() || !$reportUser->getRole()->isStaff()) {
                continue;
            }
            // check actual access level
            if ($this->accessLevelCensor->getRawAccessLevel($pastoral, $reportUser) >= $accessLevel) {
                $targetUsers->offsetSet($reportUser->getId(), $reportUser);
            }
        }
        return $targetUsers;
    }
}
