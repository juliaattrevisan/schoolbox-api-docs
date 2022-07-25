<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Alaress\Database;
use Alaress\Database\Predicate\InvalidColumnException;
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
use UnderflowException;
use UnexpectedValueException;

use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function count;
use function implode;
use function in_array;

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
        GenericCollection $simpleStrategies,
        Complex\Mapper $complexMapper,
        News\Mapper $newsMapper
    ) {
        $this->validNullEntities  = $validNullEntities;
        $this->modifierEntityMap  = $modifierEntityMap;
        $this->db                 = $db;
        $this->folderMapper       = $folderMapper;
        $this->relationshipFilter = $relationshipFilter;
        $this->simpleStrategies   = $simpleStrategies;
        $this->complexMapper      = $complexMapper;
        $this->articleMapper      = $newsMapper;
    }


    /**
     * Returns the set of articles whose audience contains the specified user
     *
     * @param User     $user
     * @param int|null $state If provided, find only IDs of articles in this state
     * @param bool     $savedOnly If true, returns only saved articles
     * @return int[]
     * @throws UnderflowException
     * @throws InvalidColumnException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws Database\Exception
     */
    public function findArticlesForUser(User $user, ?int $state, bool $savedOnly): array
    {
        /* Business Rules + Algorithm:
         * * An articles is targeted to 0:N audience segments.
         * * An audience segment may be simple or complex.
         * * Simple segments are targeted to a entity type and value.
         * * Complex segments are composed of two entity type/value pairs
         * * A complex segment matches a user if both type/value pairs match the user (ie AND)
         * * A article matches a user if any root segment matches the user (ie OR)
         * * Simple segments have a relationship modifier
         * * * self        => user must match segment
         * * * parents_of  => user's child must match segment
         * * * teachers_of => user's students must match segment
         * * For users with role type 'student' or 'guest', the user can only match:
         * * 1. segments targeted to 'self'
         * * For users with role type 'parent', the user can match:
         * * 1. segments targeted to 'self' if the segment matches themselves, or
         * * 2. segments targeted to 'parents_of' if the segment matches one of the user's children
         * * For users with role type 'staff', the user can match:
         * * 1. segments targeted to 'self' if the segment matches themselves, or
         * * 2. segments targeted to 'teachers_of' if the segment matches one of their taught students
         * * Hence there are 2 independent methods for matching an article to a user:
         * * 1. Simple root segment targeted to user, or children/students of user
         * * 2. Complex root segment targeted to user
         *
         * Hence we can build 2 queries, both using the 'self' predicates, the simple segments also
         * checking 'related' user attributes, then UNION ALL them together.
         */

        $selfAttributes    = $this->buildSelfAttributes($user);
        $relatedAttributes = $this->buildRelatedAttributes($user);

        // Fetch all the data we need for our query from
        // * the user, and
        // * any relevant related users (eg students/children) if relevant (ie staff/parent)

        // Check for the unlikely case where the system is configured to not permit targeting to any segment types
        if (!$selfAttributes->count() && !$relatedAttributes->count()) {
            return [];
        }

        // We will generate a UNION of two sub-queries, the first targets simple 'self' and 'related' segments, the
        // second targets complex 'self' segments. Therefore, if only 'related' attributes are populated, we do not
        // require the second subquery.
        /** @var string[] */
        $subQueries = [];

        // We always need the simple subquery
        [$sqlSimple, $params] = $this->generateSimpleSubquery($selfAttributes, $relatedAttributes, $user, $state, $savedOnly);
        $subQueries[] = $sqlSimple;

        // We can avoid the sub-query in the unlikely case the system is configured to not permit targeting
        // to 'self', as that is the implicit relationship modifier of all complex audiences segments.
        if ($selfAttributes->count()) {
            [$sqlComplex, $paramsComplex] = $this->generateComplexSubquery($selfAttributes, $user, $state, $savedOnly);
            $subQueries[] = $sqlComplex;
            $params       = array_merge($params, $paramsComplex);
        }

        $query = implode("\nUNION\n", $subQueries);

        $hydrator = $this->articleMapper->getColumnScalarHydrator($this->articleMapper->getIdentifierColumn());
        return array_unique(array_map(
            [$hydrator, 'hydrate'],
            $this->db->executeQuery($query, $params)->fetchAll(PDO::FETCH_COLUMN)
        ));
    }


    /**
     * Builds attributes for the user themself, suitable for converting to predicates
     *
     * @param User $user
     * @return Collection|string[]|null[] Indexed by [entityType]
     * @throws UnexpectedValueException
     */
    private function buildSelfAttributes(User $user): Collection
    {
        $selfAttributes    = new Collection();
        $validSelfEntities = $this->modifierEntityMap[Model::RELATIONSHIP_SELF];
        if (!count($validSelfEntities)) {
            return $selfAttributes;
        }
        foreach ($this->simpleStrategies as $strategy) {
            if (in_array($strategy::getEntityType(), $validSelfEntities, true)) {
                $selfValues = $strategy->getValues(new GenericCollection(User::class, [$user]));
                if (count($selfValues)) {
                    $selfAttributes[$strategy::getEntityType()] = $selfValues;
                }
            }
        }
        return $selfAttributes;
    }


    /**
     * Builds attributes for the user's related users, suitable for converting to predicates
     *
     * @param User $user
     * @return Collection|string[]|null[] Indexed by [entityType]
     * @throws InvalidColumnException
     * @throws UnexpectedValueException
     * @throws UnderflowException
     */
    private function buildRelatedAttributes(User $user): Collection
    {
        $relatedAttributes = new Collection();
        $relationship      = $this->getRelationshipModifier($user);
        if (!$relationship) {
            return $relatedAttributes;
        }
        $validRelatedEntities = $this->modifierEntityMap[$relationship];

        // Don't bother fetching related users if there's no valid relationships currently configured
        if (!count($validRelatedEntities)) {
            return $relatedAttributes;
        }
        $relatedUsers = $this->getRelatedUsers($user);

        // Short circuit if user has no related users
        if (!count($relatedUsers)) {
            return $relatedAttributes;
        }

        // Generate values suitable for insertion in query
        foreach ($this->simpleStrategies as $strategy) {
            if (in_array($strategy::getEntityType(), $validRelatedEntities, true)) {
                $values = $strategy->getValues($relatedUsers);
                if (count($values)) {
                    $relatedAttributes[$strategy::getEntityType()] = $values;
                }
            }
        }
        return $relatedAttributes;
    }


    /**
     * Returns a relationship modifier if one is appropriate for this user's role type
     *
     * @param User $user
     * @return string|null
     */
    private function getRelationshipModifier(User $user): ?string
    {
        $role = $user->getRole();
        if ($role->isStaff()) {
            return Model::RELATIONSHIP_TEACHERS_OF;
        }
        if ($role->isParent()) {
            return Model::RELATIONSHIP_PARENTS_OF;
        }
        return null;
    }


    /**
     * Returns a query suitable for matching simple audience segments
     *
     * @param Collection|string[][]|null[][] $selfAttributes
     * @param Collection|string[][]|null[][] $relatedAttributes
     * @param User                           $user
     * @param int|null                       $state
     * @param bool                           $savedOnly
     * @return array
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    private function generateSimpleSubquery(
        Collection $selfAttributes,
        Collection $relatedAttributes,
        User $user,
        ?int $state,
        bool $savedOnly = false
    ): array {
        $audienceAlias = 'simple';
        $articleAlias  = 'simple_article';

        // All articles must have at least one of the simple audiences
        $subPb = $this->articleMapper->getPredicateBuilder();
        foreach ($selfAttributes as $entityType => $entityValues) {
            $subPb = $subPb->all(
                $this->buildPredicate($audienceAlias, Model::RELATIONSHIP_SELF, $entityType, $entityValues)
            );
        }
        $relationship = $this->getRelationshipModifier($user);
        foreach ($relatedAttributes as $entityType => $entityValues) {
            // If we had $relatedAttributes, we can assume here that $relationship is non-null
            $subPb = $subPb->all($this->buildPredicate($audienceAlias, $relationship, $entityType, $entityValues));
        }

        // Also: all articles must be in the requested state (if there was one)
        $savedJoin = News\SaveForLater\Mapper::getSavedJoin($articleAlias, $savedOnly);
        $pb  = isset($state)
            ? $this->articleMapper->getPredicateForState($state, $articleAlias)
            : $this->articleMapper->getPredicateBuilder();
        $pb  = $pb->any($subPb);
        $sql = "
            SELECT     {$articleAlias}.id
            FROM       comms_news_audience {$audienceAlias}
            INNER JOIN comms_news_article {$articleAlias} ON {$audienceAlias}.comms_news_article_id = {$articleAlias}.id
            {$savedJoin}
            WHERE      {$pb->getWhereIntersect()}
        ";
        return [$sql, array_merge($pb->getParams(), News\SaveForLater\Mapper::getSavedJoinParams($user, $savedOnly))];
    }


    /**
     * Returns a query suitable for matching complex audience segments
     *
     * @param Collection|string[][]|null[][] $selfAttributes
     * @param User                           $user
     * @param int|null                       $state
     * @param bool                           $savedOnly
     * @return array
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    private function generateComplexSubquery(Collection $selfAttributes, User $user, ?int $state, bool $savedOnly = false): array
    {
        $audienceAlias = 'complex';
        $articleAlias  = 'complex_article';

        // All articles must be in one of the complex states.
        $pb1 = $this->complexMapper->getPredicateBuilder();
        $pb2 = $this->complexMapper->getPredicateBuilder();
        foreach ($selfAttributes as $entityType => $entityValues) {
            $pb1 = $pb1->all(
                $this->buildPredicate(
                    $audienceAlias,
                    null,
                    $entityType,
                    $entityValues,
                    Complex\Mapper::FIELD_ENTITY_TYPE_1,
                    Complex\Mapper::FIELD_ENTITY_VALUE_1
                )
            );
            $pb2 = $pb2->all(
                $this->buildPredicate(
                    $audienceAlias,
                    null,
                    $entityType,
                    $entityValues,
                    Complex\Mapper::FIELD_ENTITY_TYPE_2,
                    Complex\Mapper::FIELD_ENTITY_VALUE_2
                )
            );
        }

        // Also: all articles must be in the requested state (if there was one)
        $savedJoin = News\SaveForLater\Mapper::getSavedJoin($articleAlias, $savedOnly);

        $pb  = isset($state)
            ? $this->articleMapper->getPredicateForState($state, $articleAlias)
            : $this->articleMapper->getPredicateBuilder();
        $pb  = $pb->any($pb1)->any($pb2);
        $sql = "
            SELECT     {$articleAlias}.id
            FROM       comms_news_audience_complex {$audienceAlias}
            INNER JOIN comms_news_article {$articleAlias} ON {$audienceAlias}.comms_news_article_id = {$articleAlias}.id
            {$savedJoin}
            WHERE      {$pb->getWhereIntersect()}
        ";

        return [$sql, array_merge($pb->getParams(), News\SaveForLater\Mapper::getSavedJoinParams($user, $savedOnly))];
    }


    /**
     * Builds a predicate suitable for inclusion in a larger predicate
     *
     * @param string          $tableAlias
     * @param null|string     $relationship   If NULL, don't filter to this relationship
     * @param string          $entityType
     * @param string[]|null[] $entityValues
     * @param string          $colEntityType  Name of the 'entity_type' column
     * @param string          $colEntityValue Name of the 'entity_value' column
     * @return Predicate\Builder
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    private function buildPredicate(
        string $tableAlias,
        ?string $relationship,
        string $entityType,
        array $entityValues,
        string $colEntityType = Simple\Mapper::FIELD_ENTITY_TYPE,
        string $colEntityValue = Simple\Mapper::FIELD_ENTITY_VALUE
    ): Predicate\Builder {
        $outerPb = (new Predicate\Builder($this->db->getQuoter(), $tableAlias))
            ->setParamPrefix($tableAlias)
            ->equals($colEntityType, $entityType);
        if ($relationship) {
            $outerPb->equals(Simple\Mapper::FIELD_RELATIONSHIP, $relationship);
        }

        $innerPb = (new Predicate\Builder($this->db->getQuoter(), $tableAlias))->setParamPrefix($tableAlias);

        // Need an inner PB to account for nulls, eg 'entity_value IN (:nonNull) OR entity_value IS NULL'
        $matchNull = $this->simpleStrategies[$entityType]::isValidNullEntityValue() && in_array(null, $entityValues, true);
        $nonNulls  = array_filter($entityValues, static function ($value) {
            return null !== $value;
        });
        if (count($nonNulls)) {
            $innerPb->in($colEntityValue, $nonNulls);
        }
        if ($matchNull) {
            $innerPb->isNull($colEntityValue);
        }
        return $outerPb->any($innerPb);
    }


    /**
     * Returns a set of user's related (children/students) to the specified user.
     *
     * @param User $user
     * @return GenericCollection|User[]
     * @throws InvalidColumnException
     * @throws UnderflowException
     * @throws UnexpectedValueException
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
     * @throws InvalidColumnException
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
     * @throws InvalidColumnException
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
            ->buildPredicateBuilder(Simple\Mapper::TABLE_NAME)
            ->equals(Simple\Mapper::FIELD_ENTITY_TYPE, $entityType)
            ->isNull('comms_news_article.deleted_at');

        if (null === $entityValue) {
            $pb->isNull(Simple\Mapper::FIELD_ENTITY_VALUE);
        } else {
            $pb->equals(Simple\Mapper::FIELD_ENTITY_VALUE, $entityValue);
        }

        if (null !== $relationship) {
            $pb->equals(Simple\Mapper::FIELD_RELATIONSHIP, $relationship);
        }

        if ($publishedOnly) {
            $publishedPb = $this->articleMapper->getPredicatePublished();
            $pb->all($publishedPb);
        }

        // If we are not filtering by relationship, we may be duplicates, which we'll need to filter out.
        // If we are specifying the relationship, we can avoid the DISTINCT, and hence avoid a temp table.
        $distinct = null === $relationship ? 'DISTINCT' : '';

        $sql = "
            SELECT {$distinct} comms_news_article_id
            FROM comms_news_audience
            INNER JOIN comms_news_article ON comms_news_audience.comms_news_article_id = comms_news_article.id
            WHERE {$pb->getWhereIntersect()}";

        if (count($orderBy)) {
            $sql .= "
                {$this->folderMapper::buildOrderByClause(Simple\Mapper::TABLE_NAME, $orderBy, $this->db->getQuoter())}";
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
     * @param bool        $publishedOnly
     * @param array       $orderBy
     * @param int|null    $limit
     * @return int[]
     * @throws Database\Exception
     * @throws InvalidColumnException
     */
    private function findArticlesForComplexAudience(
        string $entityType1,
        ?string $entityValue1,
        string $entityType2,
        ?string $entityValue2,
        bool $publishedOnly = true,
        array $orderBy = [],
        int $limit = null
    ): array {
        $pb = $this->complexMapper->getCombinationMatchPredicate($entityType1, $entityValue1, $entityType2, $entityValue2);

        if ($publishedOnly) {
            $pb->all($this->articleMapper->getPredicatePublished());
        }

        $sql = "
            SELECT comms_news_article.id
            FROM comms_news_article
            JOIN comms_news_audience_complex ON comms_news_audience_complex.comms_news_article_id = comms_news_article.id
            WHERE {$pb->getWhereIntersect()}
        ";

        if (count($orderBy)) {
            $sql .= "\n" . Complex\Mapper::buildOrderByClause(Complex\Mapper::TABLE_NAME, $orderBy, $this->db->getQuoter());
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
