<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Alaress\Database;
use External_Class;
use External_User;
use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder;
use PDO;

/**
 * Responsible for generating a list of articles which contain a specific user in their audience.
 */
class ArticleResolver
{

    /** @var Database\Connector\IConnector */
    private $db;

    /** @var UserMapper */
    private $userMapper;

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var External_User */
    private $externalUser;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var External_Class */
    private $externalClass;


    /**
     * @param Database\Connector\IConnector $db
     * @param UserMapper                    $userMapper
     * @param UserAccessMapper              $userAccessMapper
     * @param External_User                 $externalUser
     * @param Folder\Mapper                 $folderMapper
     * @param External_Class                $externalClass
     */
    public function __construct(
        Database\Connector\IConnector $db,
        UserMapper $userMapper,
        UserAccessMapper $userAccessMapper,
        External_User $externalUser,
        Folder\Mapper $folderMapper,
        External_Class $externalClass
    ) {
        $this->db               = $db;
        $this->userMapper       = $userMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->externalUser     = $externalUser;
        $this->folderMapper     = $folderMapper;
        $this->externalClass    = $externalClass;
    }


    /**
     * @param User $user
     * @return int[]
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \InvalidArgumentException
     * @throws \UnderflowException
     */
    public function findArticlesForUser(User $user): array
    {
        /** @var Collection|string[] $whereClauses */
        $whereClauses = new Collection();

        $this->addSelfClauses($user, $whereClauses);
        $this->addParentsOfClauses($user, $whereClauses);
        $this->addTeachersOfClauses($user, $whereClauses);

        $whereClause = \implode(" OR \n", $whereClauses->getAll());

        // An article is a match for this user if either:
        // * A simple root-segment matches (as root segments are always UNION)
        // * All child segments of a root composite segment match (child segments are always INTERSECT)
        $query = "
            SELECT comms_news_article_id FROM comms_news_audience WHERE parent_id IS NULL AND ($whereClause)
                UNION
            SELECT comms_news_article_id FROM comms_news_audience cna
            JOIN (SELECT parent_id, COUNT(*) as child_count FROM comms_news_audience WHERE parent_id IS NOT NULL AND ($whereClause) GROUP BY parent_id) t2 ON t2.parent_id = cna.id
            JOIN (SELECT parent_id, COUNT(*) as child_count FROM comms_news_audience WHERE parent_id IS NOT NULL GROUP BY parent_id) t3 ON t3.parent_id = cna.id
            WHERE t3.child_count = t2.child_count
            ORDER BY comms_news_article_id;
         ";

        return $this->db->executeQuery($query)->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Adds set of clauses that will match audiences segments targeted directly to the specified user
     *
     * @param User       $user
     * @param Collection $whereClauses
     * @throws \InvalidArgumentException
     */
    private function addSelfClauses(User $user, Collection $whereClauses): void
    {
        $validEntities = SegmentRelationships::getValidEntities(Model::RELATIONSHIP_SELF);

        if (!\count($validEntities)) {
            // In the unlikely event you cannot target audience segments that lack a relationship modifier
            return;
        }

        $this->addClauses($this->userMapper->createCollection([$user]), Model::RELATIONSHIP_SELF, $validEntities, $whereClauses);
    }


    /**
     * Adds WHERE clauses that match a parent's children.
     *
     * @param User                $user
     * @param Collection|string[] $whereClauses
     * @throws \InvalidArgumentException
     */
    private function addParentsOfClauses(User $user, Collection $whereClauses): void
    {
        if (Role::PARENT !== $user->getRole()->getType()) {
            return;
        }

        $validEntities = SegmentRelationships::getValidEntities(Model::RELATIONSHIP_PARENTS_OF);
        if (!\count($validEntities)) {
            // In the system is configured to not allow targeting of parents of users in some audience segment
            return;
        }

        $externalChildren = $this->externalUser->getEnrolledStudents($user->getExternalId());

        if (!\count($externalChildren)) {
            // No children in external SIS? Then this user should never see audience segments targeted to parents of a segment
            return;
        }

        $childUsers = $this->userMapper->findByColumn('synergy_id', \array_column($externalChildren, 'RelatedID'));
        if (!\count($childUsers)) {
            // No children in schoolbox? Then this user should never see audience segments targeted to parents of a segment
            return;
        }
        $this->addClauses($childUsers, Model::RELATIONSHIP_PARENTS_OF, $validEntities, $whereClauses);
    }


    /**
     * Adds a set of WHERE clauses that will match segments targeted at this user's students.
     *
     * Note that this includes users in a teacher's classes and groups.
     *
     * @param User                $user
     * @param Collection|string[] $whereClauses
     * @throws Database\Predicate\InvalidColumnException
     * @throws \InvalidArgumentException
     * @throws \UnderflowException
     */
    private function addTeachersOfClauses(User $user, Collection $whereClauses): void
    {
        if (Role::STAFF !== $user->getRole()->getType()) {
            return;
        }

        $validEntities = SegmentRelationships::getValidEntities(Model::RELATIONSHIP_TEACHERS_OF);
        if (!\count($validEntities)) {
            // In the system is configured to not allow targeting of teachers of users in some audience segment
            return;
        }

        $taughtFolders = $this->folderMapper->findForUser($user, true, UserAccess::ACCESS_WRITE, true);

        if (!\count($taughtFolders)) {
            return;
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
            return;
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

        $taughtUsers = $this->userMapper->findByPredicates($this->userMapper->getPredicateBuilder()->any($innerPredicate));

        if (!\count($taughtUsers)) {
            // No users found in Schoolbox?
            return;
        }

        $this->addClauses($taughtUsers, Model::RELATIONSHIP_TEACHERS_OF, $validEntities, $whereClauses);
    }


    /**
     * @param Collection|User[]   $users
     * @param string              $relationship
     * @param string[]            $validEntities
     * @param Collection|string[] $whereClauses
     */
    private function addClauses(Collection $users, string $relationship, array $validEntities, Collection $whereClauses): void
    {
        $validEntities = \array_combine($validEntities, $validEntities);
        $userIds       = $users->getPropertyValues('id');

        // Targeted to a user
        if (\array_key_exists(Model::ENTITY_TYPE_USER, $validEntities)) {
            $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_USER, $userIds, $relationship));
        }

        // Targeted to superusers
        if (\array_key_exists(Model::ENTITY_TYPE_SUPER, $validEntities)) {
            foreach ($users as $user) {
                if ($user->isSuperuser()) {
                    $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_SUPER, true, $relationship));
                    break;
                }
            }
        }

        // Targeted to a campus
        if (\array_key_exists(Model::ENTITY_TYPE_CAMPUS, $validEntities)) {
            $campusIds             = [];
            $userFoundWithNoCampus = false;
            foreach ($users as $user) {
                if (!$user->getCampusCollection()->count()) {
                    $userFoundWithNoCampus = true;
                }
                foreach ($user->getCampusCollection() as $campus) {
                    $campusIds[] = $campus->getId();
                }
            }
            if ($userFoundWithNoCampus) {
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_CAMPUS, null, $relationship));
            }
            if (\count($campusIds)) {
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_CAMPUS, \array_unique($campusIds), $relationship));
            }
        }

        // Targeted to a Role
        if (\array_key_exists(Model::ENTITY_TYPE_ROLE, $validEntities)) {
            $roleIds = [];
            foreach ($users as $user) {
                $roleIds[] = $user->getRole()->getId();
            }
            $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_ROLE, \array_unique($roleIds), $relationship));
        }

        // Targeted to a RoleType
        if (\array_key_exists(Model::ENTITY_TYPE_ROLE_TYPE, $validEntities)) {
            $roleTypes = [];
            foreach ($users as $user) {
                $roleTypes[] = $user->getRole()->getType();
            }
            $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_ROLE_TYPE, \array_unique($roleTypes), $relationship));
        }

        // Targeted to a School House
        if (\array_key_exists(Model::ENTITY_TYPE_SCHOOL_HOUSE, $validEntities)) {
            $schoolHouseIds       = [];
            $userFoundWithNoHouse = false;
            foreach ($users as $user) {
                if ($user->getSchoolHouse()) {
                    $schoolHouseIds[] = $user->getSchoolHouse()->getId();
                } else {
                    $userFoundWithNoHouse = true;
                }
            }
            if (\count($schoolHouseIds)) {
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_SCHOOL_HOUSE, \array_unique($schoolHouseIds), $relationship));
            }
            if ($userFoundWithNoHouse) {
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_SCHOOL_HOUSE, null, $relationship));
            }
        }

        // Targeted to a Residential House
        if (\array_key_exists(Model::ENTITY_TYPE_RES_HOUSE, $validEntities)) {
            $residentialHouseIds  = [];
            $userFoundWithNoHouse = false;
            foreach ($users as $user) {
                if ($user->getResidentialHouse()) {
                    $residentialHouseIds[] = $user->getResidentialHouse()->getId();
                } else {
                    $userFoundWithNoHouse = true;
                }
            }
            if (\count($residentialHouseIds)) {
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_RES_HOUSE, \array_unique($residentialHouseIds), $relationship));
            }
            if ($userFoundWithNoHouse) {
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_RES_HOUSE, null, $relationship));
            }
        }

        // Targeted to a Year
        if (\array_key_exists(Model::ENTITY_TYPE_YEAR, $validEntities)) {
            $years               = [];
            $userFoundWithNoYear = false;
            foreach ($users as $user) {
                if (null !== $user->getYear()) {
                    $years[] = $user->getYear();
                } else {
                    $userFoundWithNoYear = true;
                }
            }
            if (\count($years)) {
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_YEAR, \array_unique($years), $relationship));
            }
            if ($userFoundWithNoYear) {
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_YEAR, null, $relationship));
            }
        }

        // Targeted to a service permission (including homepage learners + instructors)
        if (\array_key_exists(Model::ENTITY_TYPE_SERVICE, $validEntities)) {
            $this->addServicePermsClause($userIds, $relationship, $whereClauses);
        }

        // Targeted to a pastoral group
        if (\array_key_exists(Model::ENTITY_TYPE_PASTORAL_GROUP, $validEntities)) {
            // Get all pastoral group ids, including NULL if any user is not in any pastoral groups.
            $selects       = \array_map(function ($userId) {
                return "SELECT $userId u_id";
            }, $userIds);
            $constantTable = \implode(' UNION ', $selects);
            /** @noinspection SqlResolve */
            $sql = "
                SELECT DISTINCT group_id FROM ($constantTable) t1 LEFT JOIN pastoral_group_member pgm ON t1.u_id = pgm.user_id WHERE deleted_at IS NULL;
            ";
            // Cannot use PDO::FETCH_COLUMN to fetch NULL values.
            $groupIds = \array_column($this->db->executeQuery($sql)->fetchAll(PDO::FETCH_ASSOC), 'group_id');

            $nonNullGroupIds = \array_filter($groupIds);
            if (\count($nonNullGroupIds)) {
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_PASTORAL_GROUP, $nonNullGroupIds, $relationship));
            }
            if (\count($groupIds) !== \count($nonNullGroupIds)) {
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_PASTORAL_GROUP, null, $relationship));
            }
        }

        // Targeted to a class or group
        $canTargetGroup = \array_key_exists(Model::ENTITY_TYPE_GROUP, $validEntities);
        $canTargetClass = \array_key_exists(Model::ENTITY_TYPE_CLASS, $validEntities);
        if ($canTargetGroup || $canTargetClass) {
            $groupIds               = $classCodes = [];
            $userFoundWithoutGroups = $userFoundWithoutClasses = false;
            foreach ($users as $user) {
                $groupFound = $classFound = false;
                foreach ($this->folderMapper->findForUser($user) as $folder) {
                    if ($folder->isGroup()) {
                        $groupFound = true;
                        $groupIds[] = $folder->getId();
                    }
                    foreach ($folder->getCodes() as $code) {
                        $classFound   = true;
                        $classCodes[] = $code->getCode();
                    }
                }
                if (!$groupFound) {
                    $userFoundWithoutGroups = true;
                }
                if (!$classFound) {
                    $userFoundWithoutClasses = true;
                }
            }

            if ($canTargetGroup) {
                if (\count($groupIds)) {
                    $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_GROUP, \array_unique($groupIds), $relationship));
                }
                if ($userFoundWithoutGroups) {
                    $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_GROUP, null, $relationship));
                }
            }
            if ($canTargetClass) {
                if (\count($classCodes)) {
                    $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_CLASS, \array_unique($classCodes), $relationship));
                }
                if ($userFoundWithoutClasses) {
                    $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_CLASS, null, $relationship));
                }
            }
        }
    }


    /**
     * Adds a predicates that will match audience segments to a user's service permissions
     *
     * @param int[]               $userIds
     * @param string              $relationship
     * @param Collection|string[] $whereClauses
     */
    private function addServicePermsClause(array $userIds, string $relationship, Collection $whereClauses): void
    {
        // TODO For now, assume that we cannot target to audiences such as 'all service moderators' ('*.*.3') etc
        if (!\count($userIds)) {
            return;
        }
        $entityValue = [];

        foreach ($this->userAccessMapper->findByColumn('uid', $userIds) as $access) {
            $entityValue[] = "{$access->getService()}.{$access->getServiceIdentifier()}.{$access->getAccessLevel()}";
        }
        if (\count($entityValue)) {
            $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_SERVICE, \array_unique($entityValue), $relationship));
        }
    }


    /**
     * Helper function to build a predicate
     *
     * @param string                $entityType
     * @param null|array|string|int $entityValue
     * @param string                $relationship
     * @return string
     */
    private function buildClause(string $entityType, $entityValue, string $relationship): string
    {
        $colEntityType  = Mapper::FIELD_ENTITY_TYPE;
        $colEntityValue = Mapper::FIELD_ENTITY_VALUE;
        if (null === $entityValue) {
            $predicate = 'IS NULL';
        } elseif (\is_iterable($entityValue)) {
            $quotedValues = array_map(function ($item) {
                return "'$item'";
            }, $entityValue);
            $valueString  = \implode(',', $quotedValues);
            $predicate    = "IN ({$valueString})";
        } else {
            $predicate = "= '$entityValue'";
        }
        return "($colEntityType = '$entityType' AND relationship = '$relationship' AND $colEntityValue {$predicate})";
    }
}
