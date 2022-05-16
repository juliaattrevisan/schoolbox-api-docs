<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Code\SubjectMatcherBase;
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
                $parentExternalIds[] = $userContact['StudentContactID'];
            }
            if ($userContact['StudentContactSpouseID']) {
                $parentExternalIds[] = $userContact['StudentContactSpouseID'];
            }
        }

        if (!\count($parentExternalIds)) {
            return [];
        }

        $externalIdBindings = Builder::getArrayBindings($parentExternalIds);

        $query = "
            SELECT u.id
            FROM user u
            JOIN `groups` g on u.group_id = g.id
            WHERE u.synergy_id IN ({$externalIdBindings['placeholders']})
            AND g.group_type = ?
            AND u.deleted_at IS NULL
        ";

        return array_map('intval', $this->db->executeQuery($query, array_merge($externalIdBindings['bindings'], [Role::PARENT]))->fetchAll(PDO::FETCH_COLUMN));
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
     * This no longer returns 'write' access members of groups in which the students involve (SA-10020)
     *
     * @param int[] $userIds
     * @return int[]
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function getTeachers(array $userIds): array
    {
        if (!\count($userIds)) {
            return $this->userMapper->createCollection();
        }

        $pb = $this->userMapper->getPredicateBuilder()
                               ->in('id', $userIds)
                               ->equals('g.group_type', Role::STUDENT);

        $students = $this->userMapper->findByPredicates($pb);

        $classCodes  = [];
        foreach ($students as $student) {
            // Find all folders where the user is a class student or learner-only.
            $subjects = $this->externalUser->getSubjectList($student);
            foreach ($subjects as $subject) {
                $classCode = SubjectMatcherBase::generateFolderCode($subject['campusCode'], $subject['code']);
                if (!in_array($classCode, $classCodes)) {
                    $classCodes[] = $classCode;
                }
            }
        }

        // Students are not in any groups/classes?
        if (!count($classCodes)) {
            // Then they have no teachers
            return [];
        }

        // Then find the teachers of these classes from synergetic.
        $teacherExternalIds = $this->externalClass->getTeachers(\implode(',', $classCodes));


        // No class teachers or group instructors?
        if (!\count($teacherExternalIds)) {
            return [];
        }

        $innerPb = $this->userMapper->getPredicateBuilder();
        $innerPb->in('synergy_id', $teacherExternalIds);

        $outerPb = $this->userMapper->getPredicateBuilder()
                                    ->any($innerPb)
                                    ->equals('g.group_type', Role::STAFF);

        return array_map('intval', $this->userMapper->findByPredicates($outerPb)->getPropertyValues('id'));
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

        // Get all the codes from all the folders
        $folderCodes = [];
        foreach ($taughtFolders as $taughtFolder) {
            foreach ($taughtFolder->getCodes() as $code) {
                $folderCodes[$code->getCode()] = $code->getCode();
            }
        }

        // Find students for these folders codes
        $classStudentExternalIds = [];
        if (\count($folderCodes) > 0) {
            foreach ($this->externalClass->getStudents(\implode(',', $folderCodes)) as $classStudent) {
                $classStudentExternalIds[$classStudent['synergy_id']] = $classStudent['synergy_id'];
            }
        }

        if (!\count($classStudentExternalIds)) {
            return $this->userMapper->createCollection();
        }

        $outerPb = $this->userMapper->getPredicateBuilder()
                                    ->in('synergy_id', $classStudentExternalIds)
                                    ->equals('g.group_type', Role::STUDENT);

        return $this->userMapper->findByPredicates($outerPb);
    }
}
