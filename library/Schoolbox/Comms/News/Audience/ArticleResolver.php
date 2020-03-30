<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
use function array_keys;
use function array_map;

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

    /** @var Complex\Mapper */
    private $complexMapper;

    /** @var News\Mapper */
    private $articleMapper;


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
        $this->complexMapper      = Complex\Mapper::getInstance(); // TODO inject
        $this->articleMapper      = News\Mapper::getInstance(); // TODO inject
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
         * * Composite segments are composed of 2 simple segments.
         * * Audience segments may be root segments or child segments.
         * * There is a maximum of one level of nesting (ie no children of child segments).
         * * Hence, child segments are always simple.
         * * A composite segment matches a user if all its child segments match the user (ie AND)
         * * A article matches a user if any root segment matches the user (ie OR)
         * * Segments have a relationship modifier
         * * * self        => user must match segment
         * * * parents_of  => user's child must match segment
         * * * teachers_of => user's students must match segment
         * * Composite segments do not have modifiers on either the parent or children
         * * For users with role type 'student' or 'guest', they can only match:
         * * 1. segments targeted to 'self'
         * * For users with role type 'parent', they can match:
         * * 1. segments targeted to 'self' if the segment matches themselves, or
         * * 2. segments targeted to 'parents_of' if the segment matches one of their children
         * * For users with role type 'teacher', they can match:
         * * 1. segments targeted to 'self' if the segment matches themselves, or
         * * 2. segments targeted to 'teachers_of' if the segment matches one of their taught students
         * * Hence there are 2 independent methods for matching an article to a user:
         * * 1. Simple root segment targeted to user, or children/students of user
         * * 2. Composite root segment targeted to user, composed of child segments that target user
         *
         * Hence we can build 2 queries, both using the 'self' predicates, the simple segments also
         * checking 'related' user attributes, then UNION ALL them together.
         */

        /**
         * @var Collection|string[]|null[] $selfAttributes     Indexed by [entityType]
         * @var Collection|string[]|null[] $relatedAttributes  Indexed by [entityType]
         * @var Collection|mixed[]         $whereParams
         */
        $selfAttributes    = new Collection();                  // ['folder' => [1,2,3], 'role' => [1,2,3], ...]
        $relatedAttributes = new Collection();                  // ['folder' => [1,2,3], 'role' => [1,2,3], ...]
        $whereParams       = new Collection();

        // Fetch all the data we need for our query from
        // * the user, and
        // * any relevant related users (eg students/children) if relevant (ie staff/parent)

        // Build query for user themself.
        $validSelfEntities = $this->modifierEntityMap[Model::RELATIONSHIP_SELF];
        if (\count($validSelfEntities)) {
            foreach ($this->simpleStrategies as $strategy) {
                if (\in_array($strategy::getEntityType(), $validSelfEntities, true)) {
                    $selfAttributes[$strategy::getEntityType()] = $strategy->getValues(new GenericCollection(User::class, [$user]));
                }
            }
        }
        $selfWhereClauses = $this->buildWhereClauses($selfAttributes, Model::RELATIONSHIP_SELF, $whereParams);

        // Build query for related users?
        $role       = $user->getRole();
        $hasRelated = $role->isParent() || $role->isStaff();
        if ($hasRelated) {
            $relationship         = $role->isParent() ? Model::RELATIONSHIP_PARENTS_OF : Model::RELATIONSHIP_TEACHERS_OF;
            $validRelatedEntities = $this->modifierEntityMap[$relationship];

            if (\count($validRelatedEntities)) {
                // Don't bother fetching these if there's no valid relationships currently configured
                $relatedUsers = $this->getRelatedUsers($user);

                if (\count($relatedUsers)) {
                    foreach ($this->simpleStrategies as $strategy) {
                        if (\in_array($strategy::getEntityType(), $validRelatedEntities, true)) {
                            $relatedAttributes[$strategy::getEntityType()] = $strategy->getValues($relatedUsers);
                        }
                    }
                }
            }
            $relatedWhereClauses = $this->buildWhereClauses($relatedAttributes, $relationship, $whereParams);
        } else {
            $relatedWhereClauses = [];
        }

        // Check for the unlikely case where the system is configured to not permit targeting to any segment types
        if (!$selfAttributes->count() && !$relatedAttributes->count()) {
            return [];
        }

        // Simple segments permit related modifiers, composites do not
        $simpleClause  = "\n" . \implode(" OR \n", \array_merge($selfWhereClauses, $relatedWhereClauses));
        $complexClause = "\n" . \implode(" OR \n", $selfWhereClauses);

        $parameters = $whereParams->getAll();

        $query = "
            SELECT comms_news_article_id
            FROM comms_news_audience
            WHERE parent_id IS NULL AND ({$simpleClause})

            UNION

            SELECT parent.comms_news_article_id
            FROM comms_news_audience
            JOIN comms_news_audience parent ON comms_news_audience.parent_id = parent.id
            WHERE ({$complexClause})
            GROUP BY comms_news_audience.parent_id
            HAVING COUNT(comms_news_audience.parent_id) = 2;
        ";
        return array_map('intval', $this->db->executeQuery($query, $parameters)->fetchAll(PDO::FETCH_COLUMN));
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
        foreach ($attributes as $entityType => $values) {
            $matchNull = $this->simpleStrategies[$entityType]::isValidNullEntityValue() && \in_array(null, $values, true);
            $filtered  = \array_filter($values, function ($value) {
                return null !== $value;
            });
            $parts     = [];
            if (\count($filtered)) {
                $prefix   = "{$entityType}_{$relationship[0]}";
                $bindings = Predicate\Builder::getBindings($filtered, $prefix);
                $parts[]  = "$table.$colEntityValue IN ({$bindings->getPlaceholder()})";
                foreach ($bindings->getParams() as $placeholder => $val) {
                    $whereParams[$placeholder] = $val;
                }
            }
            if ($matchNull) {
                $parts[] = "$table.$colEntityValue IS NULL";
            }
            if (\count($parts)) {
                $whereClauses[] = "($table.$colRelationship = '$relationship' AND $table.$colEntityType = '$entityType' AND (" . \implode(' OR ', $parts) . "))";
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
     * @inheritdoc
     * @throws InvalidArgumentException
     * @throws Predicate\InvalidColumnException
     * @throws Database\Exception
     */
    public function findArticlesForAudience(
        string $entityType,
        $entityValue,
        ?string $relationship,
        bool $publishedOnly = true,
        array $orderBy = [],
        int $limit = null
    ): array {
        // Simple equality case
        if (Strategy\Composite::getEntityType() !== $entityType) {
            return $this->findArticlesForSimpleAudience($entityType, $entityValue, $relationship, $publishedOnly, $orderBy, $limit);
        }

        // Complex intersect case
        if (!\is_array($entityValue) || count($entityValue) !== 2) {
            throw new InvalidArgumentException('If performing a complex search, $entityValue must be an array of two type:value pairs');
        }
        [$entityType1, $entityType2] = array_keys($entityValue);

        return $this->findArticlesForComplexAudience(
            $entityType1,
            $entityValue[$entityType1],
            $entityType2,
            $entityValue[$entityType2],
            $relationship,
            $publishedOnly,
            $orderBy,
            $limit
        );
    }


    /**
     * Returns article ids matching specified simple audience
     *
     * @param string      $entityType
     * @param string|null $entityValue
     * @param string|null $relationship NULL means don't filter by
     * @param bool        $publishedOnly
     * @param array       $orderBy
     * @param int|null    $limit
     * @return int[]
     * @throws Database\Exception
     * @throws Predicate\InvalidColumnException
     */
    private function findArticlesForSimpleAudience(
        string $entityType,
        ?string $entityValue,
        ?string $relationship,
        bool $publishedOnly = true,
        array $orderBy = [],
        int $limit = null
    ): array {

        // Don't attempt to match a segment if the system is not configured to permit null entity values for the given entity type
        if (null === $entityValue && !$this->validNullEntities[$entityType]) {
            return [];
        }

        $pb = $this->db
            ->buildPredicateBuilder(Mapper::TABLE_NAME)
            ->equals(Mapper::FIELD_ENTITY_TYPE, $entityType)
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
            $publishedPb = $this->articleMapper->getPredicatePublished();
            $pb->all($publishedPb);
        }

        $whereClause = \implode(' AND ', $pb->getWhere());

        // If we are not filtering by relationship, we may be duplicates, which we'll need to filter out.
        // If we are specifying the relationship, we can avoid the DISTINCT, and hence avoid a temp table.
        $distinct = null === $relationship ? 'DISTINCT' : '';

        $sql = "
            SELECT {$distinct} comms_news_article_id
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

        $hydrator = $this->articleMapper->getColumnScalarHydrator($this->articleMapper->getIdentifierColumn());
        return array_map([
            $hydrator,
            'hydrate',
        ], $this->db->executeQuery($sql, $pb->getParams())->fetchAll(PDO::FETCH_COLUMN));
    }


    /**
     * @param string      $entityType1
     * @param string|null $entityValue1
     * @param string      $entityType2
     * @param string|null $entityValue2
     * @param string|null $relationship
     * @param bool        $publishedOnly
     * @param array       $orderBy
     * @param int|null    $limit
     * @return int[]
     * @throws Database\Exception
     * @throws Predicate\InvalidColumnException
     */
    private function findArticlesForComplexAudience(
        string $entityType1,
        ?string $entityValue1,
        string $entityType2,
        ?string $entityValue2,
        ?string $relationship,
        bool $publishedOnly = true,
        array $orderBy = [],
        int $limit = null
    ): array {

        $pb = $this->complexMapper->getCombinationMatchPredicate($entityType1, $entityValue1, $entityType2, $entityValue2);

        if ($relationship !== null) {
            $pb->equals(Complex\Mapper::FIELD_RELATIONSHIP, $relationship);
        }
        if ($publishedOnly) {
            $pb->all($this->articleMapper->getPredicatePublished());
        }
        $pb = $this->articleMapper->getPredicateBuilder()->all($pb);

        // If we are not filtering by relationship, we may be duplicates, which we'll need to filter out.
        // If we are specifying the relationship, we can avoid the DISTINCT, and hence avoid a temp table.
        $distinct = null === $relationship ? 'DISTINCT' : '';

        $sql = "
            SELECT {$distinct} comms_news_article.id
            FROM comms_news_article
            JOIN comms_news_audience_complex ON comms_news_audience_complex.comms_news_article_id = comms_news_article.id
            WHERE {$pb->getWhere()[0]}
        ";

        if (\count($orderBy)) {
            $sql .= "\n{$this->folderMapper::buildOrderByClause(Mapper::TABLE_NAME, $orderBy, $this->db)}";
        }

        if (null !== $limit) {
            $sql .= "
                LIMIT :limit";
            $pb->putParam('limit', $limit);
        }
        $hydrator = $this->articleMapper->getColumnScalarHydrator($this->articleMapper->getIdentifierColumn());
        return array_map([
            $hydrator,
            'hydrate',
        ], $this->db->executeQuery($sql, $pb->getParams())->fetchAll(PDO::FETCH_COLUMN));
    }
}
