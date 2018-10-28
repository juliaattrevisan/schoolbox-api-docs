<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Alaress\Database;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use PDO;
use Alaress\Database\Predicate;
use Schoolbox\Comms\News\Audience\Segment\Strategy;
use InvalidArgumentException;
use Schoolbox\Comms\News;

/**
 * Responsible for returning the set of articles whose audience contains a specific user.
 */
class ArticleResolver implements IArticleResolver
{
    /**
     * Indexed by segment.entityType
     * @var bool[]
     */
    private $validNullEntities;

    /**
     * Indexed by segment.relationship
     * @var string[][]
     */
    private $modifierEntityMap;

    /** @var Database\Connector\IConnector */
    private $db;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var RelationshipFilterer */
    private $relationshipFilter;

    /** @var GenericCollection|News\Audience\Segment\ISimpleStrategy[] */
    private $simpleStrategies;


    /**
     * @param bool[]                        $validNullEntities
     * @param array                         $modifierEntityMap
     * @param Database\Connector\IConnector $db
     * @param Folder\Mapper                 $folderMapper
     * @param RelationshipFilterer          $relationshipFilter
     * @param GenericCollection             $simpleStrategies
     */
    public function __construct(
        array $validNullEntities,
        array $modifierEntityMap,
        Database\Connector\IConnector $db,
        Folder\Mapper $folderMapper,
        RelationshipFilterer $relationshipFilter,
        GenericCollection $simpleStrategies
    ) {
        $this->validNullEntities  = $validNullEntities;
        $this->modifierEntityMap  = $modifierEntityMap;
        $this->db                 = $db;
        $this->folderMapper       = $folderMapper;
        $this->relationshipFilter = $relationshipFilter;
        $this->simpleStrategies   = $simpleStrategies;
    }


    /**
     * Returns the set of articles whose audience contains the specified user
     *
     * @param User $user
     * @return int[]
     * @throws \UnderflowException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
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
         * @var Collection|string[]|null[] $selfAttributes     Indexed by [entityType]
         * @var Collection|string[]|null[] $relatedAttributes  Indexed by [entityType]
         */
        $selfAttributes    = new Collection();                  // ['folder' => [1,2,3], 'role' => [1,2,3], ...]
        $relatedAttributes = new Collection();                  // ['folder' => [1,2,3], 'role' => [1,2,3], ...]

        // Do we need to check related users attributes?
        $role = $user->getRole();
        $hasRelated = $role->isParent() || $role->isStaff();

        // Fetch all the data we need for our query from
        // * the user, and
        // * any relevant related users (eg students/children) if relevant (ie staff/parent)

        $validSelfEntities = $this->modifierEntityMap[Model::RELATIONSHIP_SELF];
        if (\count($validSelfEntities)) {
            foreach ($this->simpleStrategies as $strategy) {
                if (\in_array($strategy::getEntityType(), $validSelfEntities, true)) {
                    $selfAttributes[$strategy::getEntityType()] = $strategy->getValues(new GenericCollection(User::class, [$user]));
                }
            }
        }

        if ($hasRelated) {

            $validRelatedEntities = $this->modifierEntityMap[$role->isParent()
                ? Model::RELATIONSHIP_PARENTS_OF
                : Model::RELATIONSHIP_TEACHERS_OF];

            if (\count($validRelatedEntities)) {
                // Don't bother fetching these if there's no valid relationships currently configured
                $relatedUsers = $this->getRelatedUsers($user);

                if(\count($relatedUsers)) {
                    foreach ($this->simpleStrategies as $strategy) {
                        if (\in_array($strategy::getEntityType(), $validRelatedEntities, true)) {
                            $relatedAttributes[$strategy::getEntityType()] = $strategy->getValues($relatedUsers);
                        }
                    }
                }
            }
        }


        // Check for the unlikely case where the system is configured to not permit targeting to any segment types
        if (!$selfAttributes->count() && !$relatedAttributes->count()) {
            return [];
        }

        // Build the clauses and params
        $whereParams = new Collection();
        $selfWhereClauses = $this->buildWhereClauses($selfAttributes, Model::RELATIONSHIP_SELF, $whereParams);

        if($hasRelated){
            $relationship = $role->isParent() ? Model::RELATIONSHIP_PARENTS_OF : Model::RELATIONSHIP_TEACHERS_OF;
            $relatedWhereClauses = $this->buildWhereClauses($relatedAttributes, $relationship, $whereParams);
        } else {
            $relatedWhereClauses = new Collection();
        }

        // Simple segments permit related modifiers, composites do not
        $simpleClause  = "\n" . \implode(" OR \n", \array_merge($selfWhereClauses, $relatedWhereClauses));
        $complexClause = "\n" . \implode(" OR \n", $selfWhereClauses);

        $parameters  = $whereParams->getAll();

        $query = "
            SELECT comms_news_article_id
            FROM comms_news_audience
            WHERE parent_id IS NULL AND ({$simpleClause})

            UNION 

            SELECT parent.comms_news_article_id
            FROM comms_news_audience comms_news_audience
            JOIN comms_news_audience parent ON comms_news_audience.parent_id = parent.id
            WHERE ({$complexClause})
            GROUP BY comms_news_audience.parent_id
            HAVING COUNT(comms_news_audience.parent_id) = 2;
        ";

        return $this->db->executeQuery($query, $parameters)->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Accepts a set of user attributes and a relationship modifier, and converts to a set of WHERE clauses.
     *
     * Inserts any bound parameters values into $whereParams
     *
     * @param Collection $attributes
     * @param string     $relationship
     * @param Collection $whereParams
     * @return string[]
     */
    private function buildWhereClauses(Collection $attributes, string $relationship, Collection $whereParams): array
    {
        $table           = Mapper::TABLE_NAME;
        $colEntityType   = Mapper::FIELD_ENTITY_TYPE;
        $colRelationship = Mapper::FIELD_RELATIONSHIP;
        $colEntityValue  = Mapper::FIELD_ENTITY_VALUE;

        $whereClauses = [];
        foreach($attributes as $entityType => $values){
            $matchNull = $this->simpleStrategies[$entityType]::isValidNullEntityValue() && \in_array(null, $values, true);
            $filtered  = \array_filter($values, function ($value) {
                return null !== $value;
            });
            $parts = [];
            if(\count($filtered)){
                $prefix  = "{$entityType}_{$relationship[0]}";
                $bindings = Predicate\Builder::getBindings($filtered, $prefix);
                $parts[]  = "$table.$colEntityValue IN ({$bindings->getPlaceholder()})";
                foreach ($bindings->getParams() as $placeholder => $val) {
                    $whereParams[$placeholder] = $val;
                }
            }
            if($matchNull){
                $parts[] = "$table.$colEntityValue IS NULL";
            }
            if(\count($parts)){
                $whereClauses[] = "($table.$colRelationship = '$relationship' AND $table.$colEntityType = '$entityType' AND (". \implode(' OR ', $parts) ."))";
            }
        }
        return $whereClauses;
    }



    /**
     * @param User $user
     * @return GenericCollection|User[]
     * @throws Predicate\InvalidColumnException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    private function getRelatedUsers(User $user): GenericCollection
    {
        $relatedUsers = new GenericCollection(User::class);
        $role         = $user->getRole();

        if (Role::PARENT === $role->getType()) {
            foreach ($this->relationshipFilter->getChildren($user) as $child) {
                $relatedUsers[$child->getId()] = $child;
            }
        } elseif (Role::STAFF === $role->getType()) {
            foreach ($this->relationshipFilter->getTaughtStudentsAndLearners($user) as $student) {
                $relatedUsers[$student->getId()] = $student;
            }
        }
        return $relatedUsers;
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
        if (!$this->validNullEntities[$entityType] && (null === $entityValue || (\is_iterable($entityValue) && !\count($entityValue)))) {
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
     * @inheritdoc
     * @throws InvalidArgumentException
     * @throws Predicate\InvalidColumnException
     * @todo Currently only working for $relationship == 'self'. Need to update for other relationship types.
     * @todo This is written recursively for composite audiences. It should be updated to do this in a single db query.
     */
    public function findArticlesForAudience(string $entityType, $entityValue, ?string $relationship, bool $publishedOnly = true, array $orderBy = [], int $limit = null): array
    {
        // Simple equality case
        if (Strategy\Composite::getEntityType() !== $entityType) {
            $pb = $this->db
                ->buildPredicateBuilder(Mapper::TABLE_NAME)
                ->equals(Mapper::FIELD_ENTITY_TYPE, $entityType)
                ->isNull(Mapper::FIELD_PARENT_ID)
                ->isNull('comms_news_article.deleted_at');

            if (null === $entityValue) {
                $pb->isNull(Mapper::FIELD_ENTITY_VALUE);
            } else {
                $pb->equals(Mapper::FIELD_ENTITY_VALUE, $entityValue);
            }

            if (null !== $relationship) {
                $pb->equals(Mapper::FIELD_RELATIONSHIP, $relationship);
            }

            if ($publishedOnly) {
                // TODO inject mapper dependency
                $publishedPb = News\Mapper::getInstance()->getPredicatePublished();
                $pb          = $pb->all($publishedPb);
            }

            $whereClause = \implode(' AND ', $pb->getWhere());

            $sql = "
                SELECT DISTINCT comms_news_article_id
                FROM comms_news_audience
                INNER JOIN comms_news_article ON comms_news_audience.comms_news_article_id = comms_news_article.id
                WHERE {$whereClause}";

            if (\count($orderBy)) {
                $sql .= "
                    {$this->folderMapper::buildOrderByClause(Mapper::TABLE_NAME, $orderBy, $this->db)}";
            }

            if (null !== $limit) {
                $sql .= "
                    LIMIT :limit";
                $pb->putParam('limit', $limit);
            }

            return $this->db->executeQuery($sql, $pb->getParams())->fetchAll(PDO::FETCH_COLUMN);
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
            // Note we don't pass ORDER BY or LIMIT recursively
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $articleIds = \array_merge($articleIds, $this->findArticlesForAudience($type, $value, $relationship, $publishedOnly, $orderBy, $limit));
        }
        return \array_unique($articleIds);
    }
}
