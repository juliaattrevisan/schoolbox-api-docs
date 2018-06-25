<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use External_User;
use Schoolbox\Resource\Folder;
use External_Class;

/**
 * Responsible for mapping between users sets as per a segment's relationship modifier.
 *
 * This logic is encapsulates in a single class to ensure consistent treatment of relationships,
 * eg the following should be true:
 * * $teacher->getTaughtStudents()->getTeachers()->contains($teacher)
 * * $parent->getChildren()->getParents()->contains($parent)
 */
class RelationshipFilterer
{
    /** @var UserMapper */
    private $userMapper;

    /** @var External_User */
    private $externalUser;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var External_Class */
    private $externalClass;

    /** @var UserAccessMapper */
    private $userAccessMapper;


    /**
     * @param UserMapper       $userMapper
     * @param External_User    $externalUser
     * @param Folder\Mapper    $folderMapper
     * @param External_Class   $externalClass
     * @param UserAccessMapper $userAccessMapper
     */
    public function __construct(
        UserMapper $userMapper,
        External_User $externalUser,
        Folder\Mapper $folderMapper,
        External_Class $externalClass,
        UserAccessMapper $userAccessMapper
    ) {
        $this->userMapper       = $userMapper;
        $this->externalUser     = $externalUser;
        $this->folderMapper     = $folderMapper;
        $this->externalClass    = $externalClass;
        $this->userAccessMapper = $userAccessMapper;
    }


    /**
     * Return the user ids of the parents of the specified users
     *
     * @param int[] $userIds
     * @return int[]
     */
    public function getParents(array $userIds): array
    {
        /** @var \Schoolbox\User[] $users */
        $users             = $this->userMapper->findByColumn($this->userMapper->getIdentifierColumn(), $userIds);
        $parentExternalIds = [];
        foreach ($users as $user) {
            $userContacts = $this->externalUser->getStudentContactList($user->getExternalId());
            foreach ($userContacts as $userContact) {
                if ($userContact['StudentContactID']) {
                    $parentExternalIds[] = $userContact['StudentContactID'];
                }
                if ($userContact['StudentContactSpouseID']) {
                    $parentExternalIds[] = $userContact['StudentContactSpouseID'];
                }
            }
        }
        // TODO Use findScalarByPredicates
        return $this->userMapper->findByColumn('synergy_id', $parentExternalIds)->getPropertyValues($this->userMapper->getIdentifierProperty());
    }


    /**
     * Returns the child users of the specified user
     *
     * @param User $user
     * @return Collection|User[]
     */
    public function getChildren(User $user): Collection
    {
        $externalChildren = $this->externalUser->getEnrolledStudents($user->getExternalId());

        if (!\count($externalChildren)) {
            // No children in external SIS? Then this user should never see audience segments targeted to parents of a segment
            return $this->userMapper->createCollection();
        }

        return $this->userMapper->findByColumn('synergy_id', \array_column($externalChildren, 'RelatedID'));
    }


    /**
     * Get class teachers and group instructors
     *
     * @param int[] $userIds
     * @return int[]
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function getTeachersAndInstructors(array $userIds): array
    {
        $allFolders = $this->folderMapper->createCollection();
        /** @var \Schoolbox\User[] $users */
        $users              = $this->userMapper->findByColumn($this->userMapper->getIdentifierColumn(), $userIds);
        $teacherExternalIds = [];
        foreach ($users as $user) {
            // Find all folders where the user is a learner-only, or a class student.
            $getSubjects = $user->getRole()->isStudent();
            $allFolders = $allFolders->mergeUnique($this->folderMapper->findForUser($user, $getSubjects, UserAccess::ACCESS_READ, true), 'id');
        }
        // Then find the teachers of these classes from synergetic.
        // TODO are group instructors considered teachers here?
        $folderIds = [];
        foreach ($allFolders as $folder) {
            $folderIds[] = $folder->getId();
            foreach ($folder->getCodes() as $code) {
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $teacherExternalIds = \array_merge($teacherExternalIds, $this->externalClass->getTeacherName($code->getCode()));
            }
        }
        if(!\count($folderIds)){
            return [];
        }
        $instructorIds = $this->userAccessMapper->findScalarByPredicates(
            'uid',
            $this->userAccessMapper->getPredicateBuilder()
                                   ->equals('service', UserAccess::SERVICE_HOMEPAGE)
                                   ->in('sid', $folderIds)
                                   ->equals('type', UserAccess::ACCESS_WRITE)
        );

        if(!\count($teacherExternalIds) && !\count($instructorIds)){
            return [];
        }

        $innerPb = $this->userMapper->getPredicateBuilder();

        if(\count($teacherExternalIds)){
            $innerPb->in('synergy_id', $teacherExternalIds);
        }

        if(\count($instructorIds)){
            $innerPb->in('id', $instructorIds);
        }
        $outerPb = $this->userMapper->getPredicateBuilder()->any($innerPb);

        return $this->userMapper->findScalarByPredicates('id', $outerPb);
    }


    /**
     * Gets users in classes or groups taught by this user
     *
     * @param User $user
     * @return Collection|User[]
     * @throws \UnderflowException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getTaughtStudentsAndLearners(User $user): Collection
    {

        $taughtFolders = $this->folderMapper->findForUser($user, true, UserAccess::ACCESS_WRITE, true);

        if (!\count($taughtFolders)) {
            return $this->userMapper->createCollection();
        }

        $folderIds = $taughtFolders->getPropertyValues('id');

        $groupMemberIds = $this->userAccessMapper->findScalarByPredicates(
            'uid',
            $this->userAccessMapper->getPredicateBuilder()
                                   ->equals('service', UserAccess::SERVICE_HOMEPAGE)
                                   ->in('sid', $folderIds)
                                   ->equals('type', UserAccess::ACCESS_READ)
        );

        $classStudentExternalIds = [];
        foreach ($taughtFolders as $taughtFolder) {
            foreach ($taughtFolder->getCodes() as $code) {
                $classStudents = $this->externalClass->getStudents($code->getCode());
                foreach ($classStudents as $classStudent) {
                    $classStudentExternalIds[] = $classStudent['synergy_id'];
                }
            }
        }

        if (!\count($groupMemberIds) && !\count($classStudentExternalIds)) {
            return $this->userMapper->createCollection();
        }

        $innerPredicate = $this->userMapper->getPredicateBuilder();

        if (\count($groupMemberIds)) {
            $innerPredicate->in('id', $groupMemberIds);
            $innerPredicate->any(
                $this->userMapper->getPredicateBuilder()->in('id', $groupMemberIds)
            );
        }

        if (\count($classStudentExternalIds)) {
            $innerPredicate->in('synergy_id', \array_unique($classStudentExternalIds));
        }

        return $this->userMapper->findByPredicates($this->userMapper->getPredicateBuilder()->any($innerPredicate));
    }
}
