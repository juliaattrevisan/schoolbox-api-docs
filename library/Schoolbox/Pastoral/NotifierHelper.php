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
        $this->userMapper = $userMapper;
        $this->pastoralGroupMapper = $pastoralGroupMapper;
        $this->log = $log;
    }

    /**
     * Build the list of users to target.
     *
     * @param iterable<PastoralAccess> $accessRecords
     * @param bool                     $privateChangeOnly
     * @param User                     $student
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

            try {
                if ($pastoralAccess->getRole() instanceof Role) {
                    // Reload student, if possible
                    try {
                        $student = $this->userMapper->findById($student->getId());
                    } catch (MapperException $e) {
                        // noop
                        $this->log->error("Error: Unable to send {$recordType} {$method} notification. {$e->getMessage()}", ['exception' => $e]);
                        continue;
                    }

                    // The system should only notify staff if they are a teacher of the student
                    if ($pastoralAccess->getRole()->isStaff() && $pastoralAccess->getRole()->getAccessPastoral()) {
                        $student->loadTeachers();
                        foreach ($student->getTeacherCollection() as $teacher) {
                            if ($teacher->getRole()->getId() === $pastoralAccess->getRole()->getId()) {
                                $targetUsers[$teacher->getId()] = $teacher;
                            }
                        }
                    } elseif ($pastoralAccess->getRole()->isParent() && $parentAccessEnabled) {
                        // Only send to parents if pastoral parent access is enabled
                        $student->loadParents();
                        foreach ($student->getParents() as $parent) {
                            if ($parent->getRole()->getId() === $pastoralAccess->getRole()->getId()) {
                                $targetUsers[$parent->getId()] = $parent;
                            }
                        }
                    } elseif (
                        $pastoralAccess->getRole()->isStudent()
                        && $studentAccessEnabled
                        && $student->getRole()->getId() === $pastoralAccess->getRole()->getId()
                    ) {
                        // Only send to student if student access is enabled
                        // (Setting student access by role is not currently
                        //  supported in frontend: only by role type)
                        $targetUsers[$student->getId()] = $student;
                    }
                } elseif ($pastoralAccess->getRoleType()) {
                    // Reload student, if possible
                    try {
                        $student = $this->userMapper->findById($student->getId());
                    } catch (MapperException $e) {
                        // noop
                        $this->log->error("Error: Unable to send {$recordType} {$method} notification. {$e->getMessage()}", ['exception' => $e]);
                        continue;
                    }
                    switch ($pastoralAccess->getRoleType()) {
                        case Role::STAFF:
                            // Only notify student's teachers,
                            // if their role allows it
                            $student->loadTeachers();
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
                                $student->loadParents();
                                foreach ($student->getParents() as $parent) {
                                    $targetUsers[$parent->getId()] = $parent;
                                }
                            }
                            break;

                        default:
                            break;
                    }
                } elseif ($pastoralAccess->getGroup() instanceof PastoralGroup) {
                    // Sending to a pastoral group, make sure its hydrated
                    $pastoralGroup = $this->pastoralGroupMapper->findById($pastoralAccess->getGroup()->getId());

                    /** @var PastoralGroupMember $member */
                    foreach ($pastoralGroup->getMembersWithPermission() as $member) {
                        // Only staff should be in pastoral groups
                        $user = $this->userMapper->findById($member->getUser()->getId());
                        if ($user->getRole()->isStaff()) {
                            $targetUsers[$user->getId()] = $user;
                        }
                    }
                } elseif ($pastoralAccess->getUser() instanceof User) {
                    // Sending to an individual user
                    // user is not hydrated
                    $user = $this->userMapper->findById($pastoralAccess->getUser()->getId());

                    // Only send to parents if parent access is enabled,
                    // or to students if student access is enabled
                    if (
                        ($user->isStaffWithPastoralAccess())
                        || ($user->getRole()->isParent() && $parentAccessEnabled)
                        || ($user->getRole()->isStudent() && $studentAccessEnabled)
                    ) {
                        $targetUsers[$pastoralAccess->getUser()->getId()] = $pastoralAccess->getUser();
                    }
                }
            } catch (MapperException $e) {
                // Log an error and move on so we don't break the rest of the notifications
                $this->log->error("Error: Unable to send {$recordType} {$method} notification. {$e->getMessage()}", ['exception' => $e]);
            }
        }

        return $targetUsers;
    }
}
