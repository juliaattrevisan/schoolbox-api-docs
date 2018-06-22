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
        /* Business Rules + Algorithm:
         * * An articles is targeted to 0:N audience segments.
         * * An audience segment may be simple or composite.
         * * Simple segments are targeted to a entity type and value.
         * * Composite segments are composed of 1:N simple segments.
         * * Audience segments may be root segments or child segments.
         * * There is a maximum of one level of nesting (ie no children of child segments).
         * * Hence, child segments are always simple.
         * * A composite segment matches a user if all its child segments match the user (ie AND)
         * * A article matches a user if any root segment matches the user (ie OR)
         * * Segments have a relationship modifier
         * * * self        => user must match segment
         * * * parents_of  => user's child must match segment
         * * * teachers_of => user's students must match segment
         * * If a composite segment targets 'parents_of'/'teachers_of', all child segments must target 'self'.
         *   (this avoid having to find eg, children of children, which is nonsensical)
         * * For users with role type 'student' or 'guest', they can only match:
         * * 1. segments targeted to 'self'
         * * For users with role type 'parent', they can match:
         * * 1. segments targeted to 'self' if the segment matches themselves, or
         * * 2. segments targeted to 'parents_of' if the segment matches one of their children
         * * For users with role type 'teacher', they can match:
         * * 1. segments targeted to 'self' if the segment matches themselves, or
         * * 2. segments targeted to 'teachers_of' if the segment matches one of their taught students
         * * Hence there are 3 independent methods for matching an article to a user:
         * * 1. Simple root segment targeted to user, or children/students of user
         * * 2. Composite root segment targeted to user, composed of child segments that target user or children/students of user
         * * 3. Composite root segment targeted to parents_of/teachers_of, composed of segments that target children/students of user
         * * The last method does not apply if the user does not have role type of 'parent' or 'teacher'
         *
         * Hence we can build 3 queries, the first two using the same predicates, the latter with a
         * related set of predicates, then UNION ALL them together.
         */

        /**
         * @var Collection|string[] $whereClauses
         * @var Collection|string[] $relatedWhereClauses
         */
        $whereClauses        = new Collection();
        $relatedWhereClauses = new Collection();

        // Insert predicates for each segment relationship modifier.
        $this->addSelfClauses($user, $whereClauses, $relatedWhereClauses);
        $this->addParentsOfClauses($user, $whereClauses, $relatedWhereClauses);
        $this->addTeachersOfClauses($user, $whereClauses, $relatedWhereClauses);

        // Check for the unlikely case where the system is configured to not permit targeting to any segment types
        if(!$whereClauses->count()){
            return [];
        }

        $whereClause = \implode(" OR \n", $whereClauses->getAll());

        $queries = [];

        // Case 1. User matches a simple root segment
        $queries[] = "
            SELECT comms_news_article_id
            FROM comms_news_audience
            WHERE parent_id IS NULL AND (
              $whereClause
            )
        ";

        // Case 2. User matches a composite root segment, where segment is targeted to 'self'
        $queries[] = "
            SELECT t1.comms_news_article_id
            FROM (
                -- Find all matching audience sub-segments that are a child of a composite^self segment. Group by parent segment
                SELECT parent.comms_news_article_id, comms_news_audience.parent_id, COUNT(*) as child_count
                FROM comms_news_audience comms_news_audience
                JOIN comms_news_audience parent ON comms_news_audience.parent_id = parent.id
                WHERE parent.relationship = 'self' AND parent.entity_type = 'composite' AND (
                  $whereClause
                )
                GROUP BY parent.id
            ) t1
            JOIN (
                -- Group all child segments by parent to get count of parents' children.
                -- If count-matching children == count-available children, then composite segment is a match
                SELECT parent_id, COUNT(*) as child_count FROM comms_news_audience WHERE parent_id IS NOT NULL GROUP BY parent_id
            ) t2 ON t1.parent_id = t2.parent_id AND t1.child_count = t2.child_count
        ";

        // Case 3. User is parent or staff, and may match a composite root segment targeted at their children or students
        if($relatedWhereClauses->count() && ($user->getRole()->isStaff() || $user->getRole()->isParent())){
            $compositeRelationship = $user->getRole()->isStaff()
                ? Model::RELATIONSHIP_TEACHERS_OF
                : Model::RELATIONSHIP_PARENTS_OF;
            $compositeType         = Model::ENTITY_TYPE_COMPOSITE;
            $relatedWhereClause    = \implode(" OR \n", $relatedWhereClauses->getAll());
            $queries[] = "
                SELECT t1.comms_news_article_id
                FROM (
                    -- Find all matching audience sub-segments that are a child of a composite^self segment. Group by parent segment
                    SELECT parent.comms_news_article_id, comms_news_audience.parent_id, COUNT(*) as child_count
                    FROM comms_news_audience comms_news_audience
                    JOIN comms_news_audience parent ON comms_news_audience.parent_id = parent.id
                    WHERE parent.relationship = '{$compositeRelationship}' AND parent.entity_type = '{$compositeType}' AND (
                      {$relatedWhereClause}
                    )
                    GROUP BY parent.id
                ) t1
                JOIN (
                    -- Group all child segments by parent to get count of parents' children.
                    -- If count-matching children == count-available children, then composite segment is a match
                    SELECT parent_id, COUNT(*) as child_count FROM comms_news_audience WHERE parent_id IS NOT NULL GROUP BY parent_id
                ) t2 ON t1.parent_id = t2.parent_id AND t1.child_count = t2.child_count
            ";
        }

        // We are guaranteed to have independent subsets,
        $query = \implode("\nUNION ALL\n", $queries);

        return $this->db->executeQuery($query)->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Adds set of clauses that will match audiences segments targeted directly to the specified user
     *
     * @param User       $user
     * @param Collection|string[] $whereClauses
     * @param Collection|string[] $relatedWhereClauses
     */
    private function addSelfClauses(
        User $user,
        Collection $whereClauses,
        Collection $relatedWhereClauses
    ): void {
        $validEntities = SegmentRelationships::getValidEntities(Model::RELATIONSHIP_SELF);

        if (!\count($validEntities)) {
            // In the unlikely event you cannot target audience segments that lack a relationship modifier
            return;
        }

        $this->addClauses(
            $this->userMapper->createCollection([$user]),
            Model::RELATIONSHIP_SELF,
            $validEntities,
            $whereClauses,
            $relatedWhereClauses
        );
    }


    /**
     * Adds WHERE clauses that match a parent's children.
     *
     * @param User       $user
     * @param Collection|string[] $whereClauses
     * @param Collection|string[] $relatedWhereClauses
     */
    private function addParentsOfClauses(
        User $user,
        Collection $whereClauses,
        Collection $relatedWhereClauses
    ): void {
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
        $this->addClauses(
            $childUsers,
            Model::RELATIONSHIP_PARENTS_OF,
            $validEntities,
            $whereClauses,
            $relatedWhereClauses
        );
    }


    /**
     * Adds a set of WHERE clauses that will match segments targeted at this user's students.
     *
     * Note that this includes users in a teacher's classes and groups.
     *
     * @param User       $user
     * @param Collection|string[] $whereClauses
     * @param Collection|string[] $relatedWhereClauses
     */
    private function addTeachersOfClauses(
        User $user,
        Collection $whereClauses,
        Collection $relatedWhereClauses
    ): void {
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

        $this->addClauses(
            $taughtUsers,
            Model::RELATIONSHIP_TEACHERS_OF,
            $validEntities,
            $whereClauses,
            $relatedWhereClauses
        );
    }


    /**
     * @param Collection|User[]   $users
     * @param string              $relationship
     * @param string[]            $validEntities
     * @param Collection|string[] $whereClauses
     * @param Collection|string[] $relatedWhereClauses
     * @todo consider snapping each segment entity type into helper method, see 'service' example
     * @todo consider snapping common structure into helper method
     */
    private function addClauses(
        Collection $users,
        string $relationship,
        array $validEntities,
        Collection $whereClauses,
        Collection $relatedWhereClauses
    ): void {
        $validEntities = \array_combine($validEntities, $validEntities);
        $userIds       = $users->getPropertyValues('id');

        // Targeted to a user
        if (\array_key_exists(Model::ENTITY_TYPE_USER, $validEntities)) {
            $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_USER, $userIds, $relationship));
            if(Model::RELATIONSHIP_SELF !== $relationship){
                $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_USER, $userIds, Model::RELATIONSHIP_SELF));
            }
        }

        // Targeted to superusers
        if (\array_key_exists(Model::ENTITY_TYPE_SUPER, $validEntities)) {
            foreach ($users as $user) {
                if ($user->isSuperuser()) {
                    $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_SUPER, true, $relationship));
                    if(Model::RELATIONSHIP_SELF !== $relationship){
                        $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_SUPER, true, Model::RELATIONSHIP_SELF));
                    }
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
                if(Model::RELATIONSHIP_SELF !== $relationship){
                    $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_CAMPUS, null, Model::RELATIONSHIP_SELF));
                }
            }
            if (\count($campusIds)) {
                $uniqueCampusIds = \array_unique($campusIds);
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_CAMPUS, $uniqueCampusIds, $relationship));
                if(Model::RELATIONSHIP_SELF !== $relationship){
                    $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_CAMPUS, $uniqueCampusIds, Model::RELATIONSHIP_SELF));
                }
            }
        }

        // Targeted to a Role
        if (\array_key_exists(Model::ENTITY_TYPE_ROLE, $validEntities)) {
            $roleIds = [];
            foreach ($users as $user) {
                $roleIds[] = $user->getRole()->getId();
            }
            $uniqueRoleIds = \array_unique($roleIds);
            $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_ROLE, $uniqueRoleIds, $relationship));
            if(Model::RELATIONSHIP_SELF !== $relationship){
                $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_ROLE, $uniqueRoleIds, Model::RELATIONSHIP_SELF));
            }
        }

        // Targeted to a RoleType
        if (\array_key_exists(Model::ENTITY_TYPE_ROLE_TYPE, $validEntities)) {
            $roleTypes = [];
            foreach ($users as $user) {
                $roleTypes[] = $user->getRole()->getType();
            }
            $uniqueRoleTypes = \array_unique($roleTypes);
            $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_ROLE_TYPE, $uniqueRoleTypes, $relationship));
            if(Model::RELATIONSHIP_SELF !== $relationship){
                $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_ROLE_TYPE, $uniqueRoleTypes, Model::RELATIONSHIP_SELF));
            }
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
                $uniqueSchoolHouseIds = \array_unique($schoolHouseIds);
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_SCHOOL_HOUSE, $uniqueSchoolHouseIds, $relationship));
                if(Model::RELATIONSHIP_SELF !== $relationship){
                    $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_SCHOOL_HOUSE, $uniqueSchoolHouseIds, Model::RELATIONSHIP_SELF));
                }
            }
            if ($userFoundWithNoHouse) {
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_SCHOOL_HOUSE, null, $relationship));
                if(Model::RELATIONSHIP_SELF !== $relationship){
                    $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_SCHOOL_HOUSE, null, Model::RELATIONSHIP_SELF));
                }
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
                $uniqueResidentialHouseIds = \array_unique($residentialHouseIds);
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_RES_HOUSE, $uniqueResidentialHouseIds, $relationship));
                if(Model::RELATIONSHIP_SELF !== $relationship){
                    $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_RES_HOUSE, $uniqueResidentialHouseIds, Model::RELATIONSHIP_SELF));
                }
            }
            if ($userFoundWithNoHouse) {
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_RES_HOUSE, null, $relationship));
                if(Model::RELATIONSHIP_SELF !== $relationship){
                    $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_RES_HOUSE, null, Model::RELATIONSHIP_SELF));
                }
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
                $uniqueYears = \array_unique($years);
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_YEAR, $uniqueYears, $relationship));
                if(Model::RELATIONSHIP_SELF !== $relationship){
                    $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_YEAR, $uniqueYears, Model::RELATIONSHIP_SELF));
                }
            }
            if ($userFoundWithNoYear) {
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_YEAR, null, $relationship));
                if(Model::RELATIONSHIP_SELF !== $relationship){
                    $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_YEAR, null, Model::RELATIONSHIP_SELF));
                }
            }
        }

        // Targeted to a service permission (including homepage learners + instructors)
        if (\array_key_exists(Model::ENTITY_TYPE_SERVICE, $validEntities)) {
            $this->addServicePermsClause($userIds, $relationship, $whereClauses, $relatedWhereClauses);
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
                if(Model::RELATIONSHIP_SELF !== $relationship){
                    $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_PASTORAL_GROUP, $nonNullGroupIds, Model::RELATIONSHIP_SELF));
                }
            }
            if (\count($groupIds) !== \count($nonNullGroupIds)) {
                $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_PASTORAL_GROUP, null, $relationship));
                if(Model::RELATIONSHIP_SELF !== $relationship){
                    $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_PASTORAL_GROUP, null, Model::RELATIONSHIP_SELF));
                }
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
                    $uniqueGroupIds = \array_unique($groupIds);
                    $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_GROUP, $uniqueGroupIds, $relationship));
                    if(Model::RELATIONSHIP_SELF !== $relationship){
                        $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_GROUP, $uniqueGroupIds, Model::RELATIONSHIP_SELF));
                    }
                }
                if ($userFoundWithoutGroups) {
                    $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_GROUP, null, $relationship));
                    if(Model::RELATIONSHIP_SELF !== $relationship){
                        $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_GROUP, null, Model::RELATIONSHIP_SELF));
                    }
                }
            }
            if ($canTargetClass) {
                if (\count($classCodes)) {
                    $uniqueClassCodes = \array_unique($classCodes);
                    $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_CLASS, $uniqueClassCodes, $relationship));
                    if(Model::RELATIONSHIP_SELF !== $relationship){
                        $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_CLASS, $uniqueClassCodes, Model::RELATIONSHIP_SELF));
                    }
                }
                if ($userFoundWithoutClasses) {
                    $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_CLASS, null, $relationship));
                    if(Model::RELATIONSHIP_SELF !== $relationship){
                        $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_CLASS, null, Model::RELATIONSHIP_SELF));
                    }
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
     * @param Collection|string[] $relatedWhereClauses
     */
    private function addServicePermsClause(
        array $userIds,
        string $relationship,
        Collection $whereClauses,
        Collection $relatedWhereClauses
    ): void
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
            $uniqueEntityValues = \array_unique($entityValue);
            $whereClauses->add($this->buildClause(Model::ENTITY_TYPE_SERVICE, $uniqueEntityValues, $relationship));
            if(Model::RELATIONSHIP_SELF !== $relationship){
                $relatedWhereClauses->add($this->buildClause(Model::ENTITY_TYPE_SERVICE, $uniqueEntityValues, Model::RELATIONSHIP_SELF));
            }
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
        $colEntityType   = Mapper::FIELD_ENTITY_TYPE;
        $colRelationship = Mapper::FIELD_RELATIONSHIP;
        $colEntityValue  = Mapper::FIELD_ENTITY_VALUE;
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
        $table = Mapper::TABLE_NAME;
        return "($table.$colEntityType = '$entityType' AND $table.$colRelationship = '$relationship' AND $table.$colEntityValue {$predicate})";
    }
}
