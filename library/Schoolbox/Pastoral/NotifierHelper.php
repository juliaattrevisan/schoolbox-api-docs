<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral;

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccess;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Member\Model as PastoralGroupMember;
use Schoolbox\Pastoral\Group\Model as PastoralGroup;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Helper for building a list of users to target notifications for a pastoral
 * record or comment.
 */
class NotifierHelper
{
    private UserMapper $userMapper;

    private PastoralGroupMapper $pastoralGroupMapper;

    private LoggerInterface $log;

    /**
     * Constructor
     *
     * @param UserMapper          $userMapper
     * @param PastoralGroupMapper $pastoralGroupMapper
     * @param LoggerInterface     $log
     */
    public function __construct(
        UserMapper $userMapper,
        PastoralGroupMapper $pastoralGroupMapper,
        LoggerInterface $log
    ) {
        $this->userMapper          = $userMapper;
        $this->pastoralGroupMapper = $pastoralGroupMapper;
        $this->log                 = $log;
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
        string $method
    ): array {
        $targetUsers = [];

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

            // Build the list of target users
            try {
                if ($pastoralAccess->getRole() instanceof Role) {
                    $targetUsers += $this->buildTargetUsersForRole(
                        $pastoralAccess->getRole(),
                        $student,
                        $parentAccessEnabled,
                        $studentAccessEnabled,
                    );
                } elseif ($pastoralAccess->getRoleType()) {
                    $targetUsers += $this->buildTargetUsersForRoleType(
                        $pastoralAccess->getRoleType(),
                        $student,
                        $parentAccessEnabled,
                        $studentAccessEnabled,
                    );
                } elseif ($pastoralAccess->getGroup() instanceof PastoralGroup) {
                    $targetUsers += $this->buildTargetUsersForGroup(
                        $pastoralAccess->getGroup()->getId(),
                        $parentAccessEnabled,
                        $studentAccessEnabled,
                    );
                } elseif ($pastoralAccess->getUser() instanceof User) {
                    $targetUsers += $this->buildTargetUsersForUser(
                        $pastoralAccess->getUser()->getId(),
                        $parentAccessEnabled,
                        $studentAccessEnabled,
                    );
                }
            } catch (MapperException $e) {
                // Log an error and move on so we don't break the rest of the notifications
                $this->log->error("Error: Unable to send {$recordType} {$method} notification. {$e->getMessage()}", ['exception' => $e]);
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
     * @return array
     */
    private function buildTargetUsersForRole(
        Role $role,
        User $student,
        bool $parentAccessEnabled,
        bool $studentAccessEnabled
    ): array {
        $targetUsers = [];
        if ($role->isStaff() && $role->getAccessPastoral()) {
            // Only notify staff if they are a teacher of the student
            $student->loadTeachers();
            foreach ($student->getTeacherCollection() as $teacher) {
                if ($teacher->getRole()->getId() === $role->getId()) {
                    $targetUsers[$teacher->getId()] = $teacher;
                }
            }
        } elseif ($role->isParent() && $parentAccessEnabled) {
            // Only send to parents if pastoral parent access is enabled
            $student->loadParents();
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
     * @return array
     */
    private function buildTargetUsersForRoleType(
        string $roleType,
        User $student,
        bool $parentAccessEnabled,
        bool $studentAccessEnabled
    ): array {
        // The system should only notify staff if they are a teacher of the student
        $targetUsers = [];
        switch ($roleType) {
            case Role::STAFF:
                // Only notify student's teachers,
                // if their role allows it
                foreach ($student->getTeacherCollection() as $teacher) {
                    if ($teacher->getRole()->getAccessPastoral()) {
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
     * @param bool $parentAccessEnabled
     * @param bool $studentAccessEnabled
     * @return array
     * @throws MapperException if the pastoral group doesn't exist
     */
    private function buildTargetUsersForGroup(
        int $groupId,
        bool $parentAccessEnabled,
        bool $studentAccessEnabled
    ): array {
        // Sending to a pastoral group, make sure its hydrated
        $pastoralGroup = $this->pastoralGroupMapper->findById($groupId);
        $targetUsers   = [];

        /** @var PastoralGroupMember $member */
        foreach ($pastoralGroup->getMembersWithPermission() as $member) {
            // Only staff should be in pastoral groups,
            // but handle students and parents in case they are there
            try {
                $targetUsers += $this->buildTargetUsersForUser(
                    $member->getUser()->getId(),
                    $parentAccessEnabled,
                    $studentAccessEnabled,
                );
            } catch (MapperException $e) {
                // noop: silently refuse to target users which don't exist
                continue;
            }
        }
        return $targetUsers;
    }

    /**
     * Build the list of target users for a given user.
     *
     * @param int  $userId
     * @param bool $parentAccessEnabled
     * @param bool $studentAccessEnabled
     * @return array
     * @throws MapperException if the user doesn't exist
     */
    private function buildTargetUsersForUser(
        int $userId,
        bool $parentAccessEnabled,
        bool $studentAccessEnabled
    ): array {
        // Sending to a user, make sure its hydrated
        $user        = $this->userMapper->findById($userId);
        $targetUsers = [];

        // Only send to parents if parent access is enabled,
        // or to students if student access is enabled
        if (
            ($user->isStaffWithPastoralAccess())
            || ($user->getRole()->isParent() && $parentAccessEnabled)
            || ($user->getRole()->isStudent() && $studentAccessEnabled)
        ) {
            $targetUsers[$userId] = $user;
        }
        return $targetUsers;
    }
}
