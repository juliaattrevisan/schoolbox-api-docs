<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Alaress\Database;
use External_UserInterface;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use PDO;
use Alaress\Database\Predicate;
use Schoolbox\Comms\News\Audience\Segment\Strategy;
use InvalidArgumentException;

/**
 * Responsible for returning the set of articles whose audience contains a specific user.
 */
class ArticleResolver implements IArticleResolver
{

    /** @var BusinessRules */
    private $businessRules;

    /** @var Database\Connector\IConnector */
    private $db;

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var RelationshipFilterer */
    private $relationshipFilter;

    /** @var External_UserInterface */
    private $externalUser;


    /**
     * @param BusinessRules                 $businessRules
     * @param Database\Connector\IConnector $db
     * @param UserAccessMapper              $userAccessMapper
     * @param Folder\Mapper                 $folderMapper
     * @param External_UserInterface        $externalUser
     * @param RelationshipFilterer          $relationshipFilter
     */
    public function __construct(
        BusinessRules $businessRules,
        Database\Connector\IConnector $db,
        UserAccessMapper $userAccessMapper,
        Folder\Mapper $folderMapper,
        External_UserInterface $externalUser,
        RelationshipFilterer $relationshipFilter
    ) {
        $this->businessRules      = $businessRules;
        $this->db                 = $db;
        $this->userAccessMapper   = $userAccessMapper;
        $this->folderMapper       = $folderMapper;
        $this->externalUser       = $externalUser;
        $this->relationshipFilter = $relationshipFilter;
    }


    /**
     * Returns the set of articles whose audience contains the specified user
     *
     * @param User $user
     * @return int[]
     * @throws \UnderflowException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \InvalidArgumentException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
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
         * @var Collection|string[]     $whereClauses
         * @var Collection|mixed[]      $whereParams
         * @var Collection[]|string[][] $relatedWhereClauses
         */
        $whereClauses        = new Collection();
        $whereParams         = new Collection();
        $relatedWhereClauses = new GenericCollection(Collection::class);

        // Insert predicates for each segment relationship modifier.
        $this->addSelfClauses($user, $whereClauses, $whereParams);
        $this->addParentsOfClauses($user, $whereClauses, $whereParams, $relatedWhereClauses);
        $this->addTeachersOfClauses($user, $whereClauses, $whereParams, $relatedWhereClauses);

        // Check for the unlikely case where the system is configured to not permit targeting to any segment types
        if (!$whereClauses->count()) {
            return [];
        }

        $whereClause = \implode(" OR \n", $whereClauses->getAll());
        $parameters  = $whereParams->getAll();
        $queries     = [];

        // Case 1. User matches a simple root segment
        $queries[] = "
            SELECT comms_news_article_id
            FROM comms_news_audience
            WHERE parent_id IS NULL AND (
              $whereClause
            )
        ";

        // Case 2. User matches a composite root segment, where segment is targeted to 'self'
        $queries[] = $this->buildCompositeSubquery(Model::RELATIONSHIP_SELF, $whereClause);


        // Case 3. User is parent or staff, and matches a composite root segment targeted at *one* their children or students
        //         Note that we have to UNION per child/student as `[A && B]^parent/teacher !== [A]^parent/teacher && [B]^parent/teacher`
        if ($relatedWhereClauses->count()) {
            foreach ($relatedWhereClauses as $whereClauses) {
                $compositeRelationship = $user->getRole()->isStaff()
                    ? Model::RELATIONSHIP_TEACHERS_OF
                    : Model::RELATIONSHIP_PARENTS_OF;
                $whereClause           = \implode(" OR \n", $whereClauses->getAll());
                $queries[]             = $this->buildCompositeSubquery($compositeRelationship, $whereClause);
            }
        }

        // We are not guaranteed to have independent subsets, but better to remove duplicates in DB to leverage caching.
        $query = \implode("\nUNION\n", $queries);

        return $this->db->executeQuery($query, $parameters)->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Returns a sub-query that will match composite audience segments
     *
     * @param string $compositeRelationship
     * @param string $whereClause
     * @return string
     */
    private function buildCompositeSubquery(string $compositeRelationship, string $whereClause): string
    {
        $compositeType = Model::ENTITY_TYPE_COMPOSITE;

        return "
        SELECT t1.comms_news_article_id
                FROM (
                    -- Find all matching audience sub-segments that are a child of a composite^self segment. Group by parent segment
                    SELECT parent.comms_news_article_id, comms_news_audience.parent_id, COUNT(*) as child_count
                    FROM comms_news_audience comms_news_audience
                    JOIN comms_news_audience parent ON comms_news_audience.parent_id = parent.id
                    WHERE parent.relationship = '{$compositeRelationship}' AND parent.entity_type = '{$compositeType}' AND (
                      {$whereClause}
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


    /**
     * Adds set of clauses that will match audiences segments targeted directly to the specified user
     *
     * @param User                $user
     * @param Collection|string[] $whereClauses
     * @param Collection|mixed[]  $whereParams
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \InvalidArgumentException
     */
    private function addSelfClauses(User $user, Collection $whereClauses, Collection $whereParams): void
    {
        $validEntities = $this->businessRules->getValidEntities(Model::RELATIONSHIP_SELF);

        if (!\count($validEntities)) {
            // In the unlikely event you cannot target audience segments that lack a relationship modifier
            return;
        }

        $this->addClauses($user, Model::RELATIONSHIP_SELF, $validEntities, $whereClauses, $whereParams, 0);
    }


    /**
     * Adds WHERE clauses that match a parent's children.
     *
     * @param User                $user
     * @param Collection|string[] $whereClauses
     * @param Collection|mixed[]  $whereParams
     * @param Collection|string[] $relatedWhereClauses
     * @throws Database\Predicate\InvalidColumnException
     * @throws \InvalidArgumentException
     * @throws \UnderflowException
     */
    private function addParentsOfClauses(
        User $user,
        Collection $whereClauses,
        Collection $whereParams,
        Collection $relatedWhereClauses
    ): void {
        if (Role::PARENT !== $user->getRole()->getType()) {
            return;
        }

        $validEntities = $this->businessRules->getValidEntities(Model::RELATIONSHIP_PARENTS_OF);
        if (!\count($validEntities)) {
            // If the system is configured to not allow targeting of parents of users in some audience segment
            return;
        }

        $childUsers = $this->relationshipFilter->getChildren($user);
        if (!\count($childUsers)) {
            // No children in schoolbox? Then this user should never see audience segments targeted to parents of a segment
            return;
        }

        foreach ($childUsers as $idx => $childUser) {
            $paramSuffix = 1 + $idx;

            // For simple and composite^self segments
            $this->addClauses($childUser, Model::RELATIONSHIP_PARENTS_OF, $validEntities, $whereClauses, $whereParams, $paramSuffix);

            // For composite^parent_of segments
            $childUserId                       = $childUser->getId();
            $relatedWhereClauses[$childUserId] = new Collection();
            $relatedWhereParams[$childUserId]  = new Collection();
            $this->addClauses($childUser, Model::RELATIONSHIP_SELF, $validEntities, $relatedWhereClauses[$childUserId], $whereParams, $paramSuffix);
        }
    }


    /**
     * Adds a set of WHERE clauses that will match segments targeted at this user's students.
     *
     * Note that this includes users in a teacher's classes and groups.
     *
     * @param User                $user
     * @param Collection|string[] $whereClauses
     * @param Collection|mixed[]  $whereParams
     * @param Collection|string[] $relatedWhereClauses
     * @throws Predicate\InvalidColumnException
     * @throws \InvalidArgumentException
     * @throws \UnderflowException
     */
    private function addTeachersOfClauses(
        User $user,
        Collection $whereClauses,
        Collection $whereParams,
        Collection $relatedWhereClauses
    ): void {
        if (Role::STAFF !== $user->getRole()->getType()) {
            return;
        }

        $validEntities = $this->businessRules->getValidEntities(Model::RELATIONSHIP_TEACHERS_OF);
        if (!\count($validEntities)) {
            // In the system is configured to not allow targeting of teachers of users in some audience segment
            return;
        }

        $taughtUsers = $this->relationshipFilter->getTaughtStudentsAndLearners($user);
        if (!\count($taughtUsers)) {
            // No users found in Schoolbox?
            return;
        }

        foreach ($taughtUsers as $idx => $taughtUser) {

            $paramSuffix = 1 + $idx;

            // For simple and composite^self segments
            $this->addClauses($taughtUser, Model::RELATIONSHIP_TEACHERS_OF, $validEntities, $whereClauses, $whereParams, $paramSuffix);

            // For composite^teacher_of segments
            $childUserId                       = $taughtUser->getId();
            $relatedWhereClauses[$childUserId] = new Collection();
            $relatedWhereParams[$childUserId]  = new Collection();
            $this->addClauses($taughtUser, Model::RELATIONSHIP_SELF, $validEntities, $relatedWhereClauses[$childUserId], $relatedWhereParams[$childUserId], $paramSuffix);
        }
    }


    /**
     * @param User                $user
     * @param string              $relationship
     * @param string[]            $validEntities
     * @param Collection|string[] $whereClauses
     * @param Collection|mixed[]  $whereParams
     * @param int                 $paramSuffix
     * @todo consider snapping each segment entity type into helper method, see 'service' example
     * @todo consider snapping common structure into helper method
     * @throws Predicate\InvalidColumnException
     */
    private function addClauses(
        User $user,
        string $relationship,
        array $validEntities,
        Collection $whereClauses,
        Collection $whereParams,
        int $paramSuffix
    ): void {
        $validEntities = \array_combine($validEntities, $validEntities);
        $userId        = $user->getId();

        // Targeted to a user
        if (\array_key_exists(Strategy\User::ENTITY_TYPE, $validEntities)) {
            $this->buildClause($whereClauses, $whereParams, $paramSuffix, Strategy\User::ENTITY_TYPE, $userId, $relationship);
        }

        // Targeted to superusers
        if (\array_key_exists(Strategy\Super::ENTITY_TYPE, $validEntities) && $user->isSuperuser()) {
            $this->buildClause($whereClauses, $whereParams, $paramSuffix, Strategy\Super::ENTITY_TYPE, Model::ENTITY_VALUE_SUPER, $relationship);
        }

        // Targeted to a campus
        if (\array_key_exists(Strategy\Campus::ENTITY_TYPE, $validEntities) && $this->businessRules->canTargetNullEntity(Strategy\Campus::ENTITY_TYPE)) {
            $this->buildClause(
                $whereClauses,
                $whereParams,
                $paramSuffix,
                Strategy\Campus::ENTITY_TYPE,
                $user->getCampusCollection()->getPropertyValues('id'),
                $relationship
            );
        }

        // Targeted to a Role
        if (\array_key_exists(Strategy\Role::ENTITY_TYPE, $validEntities)) {
            $this->buildClause($whereClauses, $whereParams, $paramSuffix, Strategy\Role::ENTITY_TYPE, $user->getRole()->getId(), $relationship);
        }

        // Targeted to a RoleType
        if (\array_key_exists(Strategy\RoleType::ENTITY_TYPE, $validEntities)) {
            $this->buildClause($whereClauses, $whereParams, $paramSuffix, Strategy\RoleType::ENTITY_TYPE, $user->getRole()->getType(), $relationship);
        }

        // Targeted to a School House
        if (\array_key_exists(Strategy\SchoolHouse::ENTITY_TYPE, $validEntities)) {
            $schoolHouseId = $user->getSchoolHouse() ? $user->getSchoolHouse()->getId() : null;
            $this->buildClause($whereClauses, $whereParams, $paramSuffix, Strategy\SchoolHouse::ENTITY_TYPE, $schoolHouseId, $relationship);
        }

        // Targeted to a Residential House
        if (\array_key_exists(Strategy\ResHouse::ENTITY_TYPE, $validEntities)) {
            $residentialHouseId = $user->getResidentialHouse() ? $user->getResidentialHouse()->getId() : null;
            $this->buildClause($whereClauses, $whereParams, $paramSuffix, Strategy\ResHouse::ENTITY_TYPE, $residentialHouseId, $relationship);
        }

        // Targeted to a Year
        if (\array_key_exists(Strategy\Year::ENTITY_TYPE, $validEntities)) {
            $this->buildClause($whereClauses, $whereParams, $paramSuffix, Strategy\Year::ENTITY_TYPE, $user->getYear(), $relationship);
        }

        // Targeted to a service permission (including homepage learners + instructors)
        if (\array_key_exists(Strategy\Service::ENTITY_TYPE, $validEntities)) {
            // TODO - For now, assume that we cannot target to audiences such as 'all service moderators' ('*.*.3') etc
            $services = [];
            foreach ($this->userAccessMapper->findByColumn('uid', $userId) as $access) {
                $services[] = "{$access->getService()}.{$access->getServiceIdentifier()}.{$access->getAccessLevel()}";
            }
            $this->buildClause($whereClauses, $whereParams, $paramSuffix, Strategy\Service::ENTITY_TYPE, \array_unique($services), $relationship);
        }

        // Targeted to a pastoral group
        if (\array_key_exists(Strategy\PastoralGroup::ENTITY_TYPE, $validEntities)) {
            $sql      = "SELECT group_id FROM pastoral_group_member WHERE user_id = {$userId} AND deleted_at IS NULL";
            $groupIds = $this->db->executeQuery($sql)->fetchAll(PDO::FETCH_COLUMN);
            $this->buildClause($whereClauses, $whereParams, $paramSuffix, Strategy\PastoralGroup::ENTITY_TYPE, $groupIds, $relationship);
        }

        // Targeted to a folder (includes class && group association)
        if (\array_key_exists(Strategy\Folder::ENTITY_TYPE, $validEntities)) {
            // Get groups where learner or instructor (but not admin)
            $pb             = $this->userAccessMapper->getPredicateBuilder()
                                                     ->equals('uid', $userId)
                                                     ->equals('service', UserAccess::SERVICE_HOMEPAGE)
                                                     ->notEquals('type', UserAccess::ACCESS_DENY);
            $groupFolderIds = $this->userAccessMapper->findScalarByPredicates('sid', $pb);
            $classFolderIds = $this->folderMapper->findAllBySubjectList($this->externalUser->getSubjectList($user))->getPropertyValues('id');
            $foldersIds     = \array_unique(\array_merge($groupFolderIds, $classFolderIds));
            $this->buildClause($whereClauses, $whereParams, $paramSuffix, Strategy\Folder::ENTITY_TYPE, $foldersIds, $relationship);
        }
    }


    /**
     * Helper function to build a predicate.
     *
     * Empty collections will be converted to 'IS NULL' predicates.
     *
     * If entity value is NULL or empty, checks current business rules to determine if that is a valid value
     *
     * @param Collection|string[]   $whereClauses
     * @param Collection|mixed[]    $whereParams
     * @param int                   $paramSuffix So we can distinguish bound args for self and related users.
     * @param string                $entityType
     * @param null|array|string|int $entityValue
     * @param string                $relationship
     */
    private function buildClause(
        Collection $whereClauses,
        Collection $whereParams,
        int $paramSuffix,
        string $entityType,
        $entityValue,
        string $relationship
    ): void {
        // Don't attempt to match a segment if the system is not configured to permit null entity values for the given entity type
        if (!$this->businessRules->canTargetNullEntity($entityType) && (null === $entityValue || (\is_iterable($entityValue) && !\count($entityValue)))) {
            return;
        }

        $colEntityType   = Mapper::FIELD_ENTITY_TYPE;
        $colRelationship = Mapper::FIELD_RELATIONSHIP;
        $colEntityValue  = Mapper::FIELD_ENTITY_VALUE;

        if (null === $entityValue) {
            $predicate = 'IS NULL';
        } else {
            // Convert even single items to sets of 1 for consistent treatment
            if (!\is_iterable($entityValue)) {
                $entityValue = [$entityValue];
            }
            if (!\count($entityValue)) {
                $predicate = 'IS NULL';
            } else {
                $prefix       = "{$entityType}_{$paramSuffix}";
                $params       = Predicate\Builder::getArrayParameters($entityValue, $prefix);
                $placeholders = Predicate\Builder::getArrayPlaceholders($entityValue, $prefix);
                $predicate    = "IN {$placeholders}";
                foreach ($params as $placeholder => $val) {
                    $whereParams[$placeholder] = $val;
                }
            }
        }

        $table = Mapper::TABLE_NAME;
        $whereClauses->add("($table.$colEntityType = '$entityType' AND $table.$colRelationship = '$relationship' AND $table.$colEntityValue {$predicate})");
    }


    /**
     * Returns the ids of the set of articles that were targeted to this audience.
     *
     * Examples: articleA.audience = [folder:X ∪ role:Y]     // users in folderX OR roleY
     *           articleB.audience = [folder:X ∩ role:Y]     // users in folderX AND roleY
     *
     *           findArticlesForAudience([folder:X])
     *              should include articleA                  // as [folder:X] is a subset of [folder:X ∪ role:Y]
     *              should NOT include articleB              // as [folder:X] is not a subset of [folder:X ∩ role:Y]. The difference being [!folder:X ∩ role:Y]
     *
     *           findArticlesForAudience([folder:X ∪ role:Y])
     *              should include articleA                  // as [folder:X ∪ role:Y] is a subset of [folder:X ∪ role:Y]
     *              should NOT include articleB              // as [folder:X ∪ role:Y] is not a subset of [folder:X ∩ role:Y]
     *
     *           findArticlesForAudience([folder:X ∩ role:Y])
     *              should include articleA                  // as [folder:X ∩ role:Y] is a subset of [folder:X ∪ role:Y]
     *              should include articleB                  // as [folder:X ∩ role:Y] is a subset of [folder:X ∩ role:Y]
     *
     * @param string                $entityType
     * @param int|string|null|array $entityValue  Null is a valid value to match on.
     * @param string|null           $relationship Null means don't don't filter by relationship modifier.
     * @return int[]
     * @throws InvalidArgumentException If called to perform a composite search and the value does not match required format
     * @todo Currently only working for $relationship == 'self'. Need to update for other relationship types.
     * @todo This is written recursively for composite audiences. It should be updated to do this in a single db query.
     */
    public function findArticlesForAudience(string $entityType, $entityValue, string $relationship = null): array
    {
        // Simple equality case
        if (Model::ENTITY_TYPE_COMPOSITE !== $entityType) {
            $pb = $this->db
                ->buildPredicateBuilder(Mapper::TABLE_NAME)
                ->equals(Mapper::FIELD_ENTITY_TYPE, $entityType)
                ->isNull(Mapper::FIELD_PARENT_ID);

            if (null === $entityValue) {
                $pb->isNull(Mapper::FIELD_ENTITY_VALUE);
            } else {
                $pb->equals(Mapper::FIELD_ENTITY_VALUE, $entityValue);
            }

            if (null !== $relationship) {
                $pb->equals(Mapper::FIELD_RELATIONSHIP, $relationship);
            }

            $whereClause = \implode(' AND ', $pb->getWhere());

            $sql = "
                SELECT comms_news_article_id
                FROM comms_news_audience
                WHERE {$whereClause}";

            $articleIds = $this->db->executeQuery($sql, $pb->getParams())->fetchAll(PDO::FETCH_COLUMN);

            return \array_unique($articleIds);
        }

        // Complex intersect case
        if (!\is_array($entityValue)) {
            throw new InvalidArgumentException('If performing a complex search, $entityValue must be an array of type:value pairs');
        }

        if (!\count($entityValue)) {
            return [];
        }

        /* Must match all elements/children of composite rule.
         * eg A1 = [folder:x ∩ role:y ∩ year:z]
         * findArticlesForAudience([folder:x ∩ role:y]) should not return A1,  as there may be users
         * in that audience who have year != z and hence should not see article.
         */
        $whereClauses = new Collection();
        $whereParams  = new Collection();

        /** @var array $entityValue */
        foreach ($entityValue as $type => $value) {
            $this->buildClause($whereClauses, $whereParams, 0, $type, $value, Model::RELATIONSHIP_SELF);
        }

        $whereClause = \implode(' OR ', $whereClauses->getAll());

        // TODO investigate re-writing this to perform matchCount == availableCount before joining to parent composite rule to get article ids
        $sql = "
            SELECT t1.comms_news_article_id
            FROM comms_news_audience t1
            JOIN (
              -- Get child segments that match the target audience
              SELECT parent_id, count(*) AS child_count
              FROM comms_news_audience
              WHERE {$whereClause}
              AND parent_id IS NOT NULL
              GROUP BY parent_id
            ) t2 ON t1.id = t2.parent_id
            JOIN (
              -- Group all child segments by parent to get count of parents' children.
              -- If count-matching children == count-available children, then composite segment is a match
              SELECT parent_id, COUNT(*) as child_count FROM comms_news_audience WHERE parent_id IS NOT NULL GROUP BY parent_id
            ) t3 ON t1.id = t3.parent_id AND t2.child_count = t3.child_count
        ";

        // Check the exact intersect
        $articleIds = $this->db->executeQuery($sql, $whereParams->getAll())->fetchAll(PDO::FETCH_COLUMN);

        // Check more generally-audienced articles
        /** @var array $entityValue */
        foreach ($entityValue as $type => $value) {
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $articleIds = \array_merge($articleIds, $this->findArticlesForAudience($type, $value));
        }
        return \array_unique($articleIds);
    }
}
