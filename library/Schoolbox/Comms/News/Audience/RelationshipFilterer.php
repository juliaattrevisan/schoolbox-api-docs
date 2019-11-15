<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use External_UserInterface;
use Schoolbox\Resource\Folder;
use External_ClassInterface;
use PDO;

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

    /** @var IConnector */
    private $db;

    /** @var UserMapper */
    private $userMapper;

    /** @var External_UserInterface */
    private $externalUser;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var External_ClassInterface */
    private $externalClass;

    /** @var UserAccessMapper */
    private $userAccessMapper;


    /**
     * @param IConnector              $db
     * @param UserMapper              $userMapper
     * @param External_UserInterface  $externalUser
     * @param Folder\Mapper           $folderMapper
     * @param External_ClassInterface $externalClass
     * @param UserAccessMapper        $userAccessMapper
     */
    public function __construct(
        IConnector $db,
        UserMapper $userMapper,
        External_UserInterface $externalUser,
        Folder\Mapper $folderMapper,
        External_ClassInterface $externalClass,
        UserAccessMapper $userAccessMapper
    ) {
        $this->db               = $db;
        $this->userMapper       = $userMapper;
        $this->externalUser     = $externalUser;
        $this->folderMapper     = $folderMapper;
        $this->externalClass    = $externalClass;
        $this->userAccessMapper = $userAccessMapper;
    }


    /**
     * Return the user ids of the parents of the specified users
     *
     * Enforces a STUDENT:PARENT role type relationship.
     *
     * @param int[] $userIds
     * @return int[]
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     */
    public function getParents(array $userIds): array
    {
        if (!\count($userIds)) {
            return [];
        }
        $pb                = $this->userMapper->getPredicateBuilder()
                                              ->equals('g.group_type', Role::STUDENT)
                                              ->in($this->userMapper->getIdentifierColumn(), $userIds);
        $users             = $this->userMapper->findByPredicates($pb);
        $parentExternalIds = [];
        $userExternalIds = [];

        foreach ($users as $user) {
            if ($user->getExternalId()) {
                $userExternalIds[] = $user->getExternalId();
            }
        }

        if (!\count($userExternalIds)) {
            return [];
        }

        $userContacts = $this->externalUser->getStudentContactList($userExternalIds);

        foreach ($userContacts as $userContact) {
            if ($userContact['StudentContactID']) {
                $parentExternalIds[] = "'{$userContact['StudentContactID']}'";
            }
            if ($userContact['StudentContactSpouseID']) {
                $parentExternalIds[] = "'{$userContact['StudentContactSpouseID']}'";
            }
        }

        if (!\count($parentExternalIds)) {
            return [];
        }

        $roleType         = Role::PARENT;
        $externalIdString = \implode(',', $parentExternalIds);

        $query = "
            SELECT u.id
            FROM user u
            JOIN groups g on u.group_id = g.id
            WHERE u.synergy_id IN ({$externalIdString})
            AND g.group_type = '{$roleType}'
            AND u.deleted_at IS NULL
        ";

        return $this->db->executeQuery($query)->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Returns the child users of the specified user
     *
     * Enforces a PARENT:STUDENT roleType relationship
     *
     * @param User $user
     * @return Collection|User[]
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     */
    public function getChildren(User $user): Collection
    {
        if (!$user->getRole()->isParent() || !$user->getExternalId()) {
            return $this->userMapper->createCollection();
        }

        $externalChildren = $this->externalUser->getEnrolledStudents($user->getExternalId());

        if (!\count($externalChildren)) {
            // No children in external SIS? Then this user should never see audience segments targeted to parents of a segment
            return $this->userMapper->createCollection();
        }

        $pb = $this->userMapper->getPredicateBuilder()
                               ->in('synergy_id', \array_column($externalChildren, 'RelatedID'))
                               ->equals('g.group_type', Role::STUDENT);

        return $this->userMapper->findByPredicates($pb);
    }


    /**
     * Get class teachers and group instructors.
     *
     * Enforces a STUDENT:STAFF relationship between TEACHER:TAUGHT.
     *
     * This is technically irrelevant for the LEARNER:INSTRUCTOR relationship, but slightly more performance and likely
     * better reflects expected behaviour.
     *
     * @param int[] $userIds
     * @return int[]
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function getTeachersAndInstructors(array $userIds): array
    {
        if (!\count($userIds)) {
            return $this->userMapper->createCollection();
        }

        $pb = $this->userMapper->getPredicateBuilder()
                               ->in('id', $userIds)
                               ->equals('g.group_type', Role::STUDENT);

        $students = $this->userMapper->findByPredicates($pb);

        $allFolders = $this->folderMapper->createCollection();
        foreach ($students as $student) {
            // Find all folders where the user is a class student or learner-only.
            $allFolders = $allFolders->mergeUnique($this->folderMapper->findForUser($student, true, UserAccess::ACCESS_READ, true), 'id');
        }

        // Students are not in any groups/classes?
        if (!$allFolders->count()) {
            // Then they have no teachers
            return [];
        }

        // Find all class codes these students are in.
        $classCodes = [];
        $folderIds  = [];
        foreach ($allFolders as $folder) {
            $folderIds[] = $folder->getId();
            foreach ($folder->getCodes() as $code) {
                $classCodes[] = $code->getCode();
            }
        }

        // Then find the teachers of these classes from synergetic.
        $teacherExternalIds = $this->externalClass->getTeachers(\implode(',', \array_unique($classCodes)));

        $instructorIds = $this->userAccessMapper->findScalarByPredicates(
            'uid',
            $this->userAccessMapper->getPredicateBuilder()
                                   ->equals('service', UserAccess::SERVICE_HOMEPAGE)
                                   ->in('sid', $folderIds)
                                   ->equals('type', UserAccess::ACCESS_WRITE)
        );

        // No class teachers or group instructors?
        if (!\count($teacherExternalIds) && !\count($instructorIds)) {
            return [];
        }

        $innerPb = $this->userMapper->getPredicateBuilder();

        if (\count($teacherExternalIds)) {
            $innerPb->in('synergy_id', $teacherExternalIds);
        }

        if (\count($instructorIds)) {
            $innerPb->in('id', $instructorIds);
        }
        $outerPb = $this->userMapper->getPredicateBuilder()
                                    ->any($innerPb)
                                    ->equals('g.group_type', Role::STAFF);

        return $this->userMapper->findByPredicates($outerPb)->getPropertyValues('id');
    }


    /**
     * Gets users in classes or groups taught by this user.
     *
     * Enforces a STAFF:STUDENT relationship.
     *
     * Note that this only treats STAFF as being class-teachers or group-instructors.
     *
     * @param User $user
     * @return Collection|User[]
     * @throws \UnderflowException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getTaughtStudentsAndLearners(User $user): Collection
    {
        if (!$user->getRole()->isStaff()) {
            return $this->userMapper->createCollection();
        }
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

        // Get all the codes from all the folders
        $folderCodes = [];
        foreach ($taughtFolders as $taughtFolder) {
            foreach ($taughtFolder->getCodes() as $code) {
                $folderCodes[$code->getCode()] = $code->getCode();
            }
        }

        // Find students for these folders codes
        $classStudentExternalIds = [];
        if(\count($folderCodes) > 0) {
            foreach ($this->externalClass->getStudents(\implode(',', $folderCodes)) as $classStudent) {
                $classStudentExternalIds[$classStudent['synergy_id']] = $classStudent['synergy_id'];
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
            $innerPredicate->in('synergy_id', $classStudentExternalIds);
        }

        $outerPb = $this->userMapper->getPredicateBuilder()
                                    ->any($innerPredicate)
                                    ->equals('g.group_type', Role::STUDENT);

        return $this->userMapper->findByPredicates($outerPb);
    }
}
