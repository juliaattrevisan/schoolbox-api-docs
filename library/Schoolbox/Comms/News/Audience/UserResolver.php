<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use Alaress\Database;
use Alaress\Database\Predicate;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Database\Quoter\Mysql\Quoter;
use InvalidArgumentException;
use LogicException;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\Simple;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\UserAccess;
use External_ClassInterface;
use PDO;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Resource\Folder\NNIAN;
use Schoolbox\User;
use UnderflowException;
use UnexpectedValueException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use function array_merge;
use function array_unique;
use function implode;
use function in_array;
use function sprintf;
use function trigger_error;
use const E_USER_ERROR;

/**
 * Responsible for:
 * * Given an article, finding the set of users who are in its audience.
 */
class UserResolver implements IUserResolver
{

    /**
     * Indexed by segment.entityType
     * @var bool[]
     */
    private $validNullEntities;

    /** @var Database\Connector\IConnector */
    private $db;

    /** @var External_ClassInterface */
    private $externalClass;

    /** @var RelationshipFilterer */
    private $relationshipFilterer;

    /** @var Simple\Mapper */
    private $simpleSegmentMapper;

    /** @var FolderMapper */
    private $folderMapper;

    /** @var Segment\StrategyFactory */
    private $strategyFactory;

    /**
     * @param bool[]                        $validNullEntities
     * @param Database\Connector\IConnector $db
     * @param External_ClassInterface       $externalClass
     * @param RelationshipFilterer          $relationshipFilterer
     * @param Simple\Mapper                 $simpleSegmentMapper
     * @param FolderMapper                  $folderMapper
     * @param Segment\StrategyFactory       $strategyFactory
     */
    public function __construct(
        array $validNullEntities,
        Database\Connector\IConnector $db,
        External_ClassInterface $externalClass,
        RelationshipFilterer $relationshipFilterer,
        Simple\Mapper $simpleSegmentMapper,
        FolderMapper $folderMapper,
        Segment\StrategyFactory       $strategyFactory
    ) {
        $this->validNullEntities    = $validNullEntities;
        $this->db                   = $db;
        $this->externalClass        = $externalClass;
        $this->relationshipFilterer = $relationshipFilterer;
        $this->simpleSegmentMapper  = $simpleSegmentMapper;
        $this->folderMapper         = $folderMapper;
        $this->strategyFactory      = $strategyFactory;
    }


    /**
     * Returns the ids of users who are in the article's audience
     *
     * @param News\Model $article
     * @param bool       $excludeMuted set this true to exclude users who has muted folders
     * @return int[]
     * @throws Database\Exception
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws LogicException
     * @throws UnderflowException
     * @throws UnexpectedValueException If we attempt to build a query with an unrecognised entity type
     */
    public function findUsersForArticle(News\Model $article, bool $excludeMuted = false): array
    {
        $bricks = (new BrickManager($this->db))->generateBricks($article->getId());

        if(!$bricks->count()){
            return [];
        }

        $subQueries   = [];
        $allParams    = [];
        $uniquePrefix = 0;
        foreach ($bricks as $brick) {
            foreach ($brick->getRelationships() as $relationship) {
                [$sql, $params] = $this->buildDimensionsQuery(++$uniquePrefix, $relationship, $brick->getDimensions(), $excludeMuted);
                $subQueries[] = $sql;
                $allParams[]  = $params;
            }
        }

        $sql = implode("\nUNION\n", $subQueries);

        $users = [
            Model::RELATIONSHIP_SELF        => [],
            Model::RELATIONSHIP_PARENTS_OF  => [],
            Model::RELATIONSHIP_TEACHERS_OF => [],
        ];

        $query = $this->db->executeQuery($sql, count($allParams) ? array_merge(...$allParams) : []);
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $users[$row['relationship']][] = (int)$row['id'];
        }
        return array_unique(array_merge(
            $users[Model::RELATIONSHIP_SELF],
            $this->applyRelationshipFilter($users[Model::RELATIONSHIP_PARENTS_OF], Model::RELATIONSHIP_PARENTS_OF),
            $this->applyRelationshipFilter($users[Model::RELATIONSHIP_TEACHERS_OF], Model::RELATIONSHIP_TEACHERS_OF)
        ));
    }


    /**
     * Returns a SQL query + params where the query returns a single column named 'id', representing a user id.
     *
     * @param int         $subQueryId
     * @param string      $relationship
     * @param Dimension[] $dimensions
     * @param bool        $excludeMuted
     * @return array
     * @throws InvalidColumnException
     * @throws News\Exceptions\UnknownEntityTypeException
     */
    private function buildDimensionsQuery(int $subQueryId, string $relationship, array $dimensions, bool $excludeMuted): array
    {
        $userTableAlias = "user_{$subQueryId}";

        $joins  = [];
        $pb     = (new Predicate\Builder(new Quoter(), ''))->isNull("{$userTableAlias}.deleted_at");

        // Special case if:
        // * excluding muted audiences,
        // * dimensions contain folder segments, and
        // * dimensions not targeted to self.
        // We must apply the relationship modifier before removing muted users
        if($excludeMuted && $relationship !== Model::RELATIONSHIP_SELF){
            $hasFolderAudience = false;
            foreach($dimensions as $dimension){
                if($dimension->getType() === News\Audience\Segment\Strategy\Folder::getEntityType()){
                    $hasFolderAudience = true;
                    break;
                }
            }
            if($hasFolderAudience){
                return $this->buildMutedDimensionsQuery($subQueryId, $relationship, $dimensions);
            }
        }

        foreach($dimensions as $idx => $dimension){
            $strategy = $this->strategyFactory->makeSimpleStrategy($dimension->getType());
            $data     = $strategy->getUserResolutionSql($userTableAlias, "_{$subQueryId}_{$idx}", $dimension->getValues(), $excludeMuted);
            $joins[]  = $data->getJoin();
            $pb->all($data->getPb());
        }

        $joinClause = implode("\n", array_merge($joins));
        $whereClause = implode(' AND ', $pb->getWhere());
        $sql = "
            SELECT {$userTableAlias}.id, '{$relationship}' relationship
            FROM user {$userTableAlias}
            {$joinClause}
            WHERE {$whereClause}
        ";
        return [$sql, $pb->getParams()];
    }


    /**
     * Returns a SQL query + params where the query returns a single column named 'id', representing a user id.
     *
     * This is a special case where we need to resolve a relationship modifier, before stripping muted users
     *
     * @param int    $subQueryId
     * @param string $relationship A non-self modifier
     * @param array  $dimensions
     * @return array
     */
    private function buildMutedDimensionsQuery(int $subQueryId, string $relationship, array $dimensions): array
    {
        // TODO, not yet implemented.
        return ['SELECT u.id FROM user u WHERE FALSE', []];

//        foreach($dimensions as $idx => $dimension){
//            $strategy = $this->strategyFactory->makeSimpleStrategy($dimension->getType());
//            $data     = $strategy->getUserResolutionSql($userTableAlias, "_{$subQueryId}_{$idx}", $dimension->getValues(), false);
//            $joins[]  = $data->getJoin();
//            $pb->all($data->getPb());
//        }
    }


    /**
     * Returns the ids of users in an audience segment
     *
     * @param Model $audienceSegment
     * @param bool $excludeMuted
     * @return int[]
     * @throws InvalidColumnException
     * @throws UnexpectedValueException If we attempt to build a query with an unrecognised entity type
     * @throws UnderflowException
     */
    private function findUsersForAudienceSegment(Model $audienceSegment, bool $excludeMuted = false): array
    {
        $entityType = $audienceSegment->getEntityType();

        // Simple base case:
        if (Segment\Strategy\Composite::getEntityType() !== $entityType) {
            $entityValue = $audienceSegment->getEntityValue();
            if ($excludeMuted && $audienceSegment->getEntityType() === Segment\Strategy\Folder::getEntityType()) {
                try {
                    $folder = $this->folderMapper->findCheaplyById($entityValue);
                    if ($folder->getInherit()) {
                        $entityValue = (string)NNIAN::getInstance($this->folderMapper)->findNearestNonInheritingAncestralNode($folder)->getId();
                    }
                } catch (Exception $e) {
                    trigger_error(E_USER_ERROR, $e->getMessage() . $e->getTraceAsString());
                    return [];
                }
            }
            $userIds = $this->getUserIds($audienceSegment->getEntityType(), $entityValue);
            $userIds = $this->applyRelationshipFilter($userIds, $audienceSegment->getRelationship());

            // Exclude the users who has muted the current folder.
            if ($excludeMuted && $audienceSegment->getEntityType() === Segment\Strategy\Folder::getEntityType()) {
                $parameters = [
                    'routeKey' => sprintf('service.%s.%s', UserAccess::SERVICE_HOMEPAGE, $entityValue),
                    'delivery' => RoutePermission::MUTE,
                ];
                $mutedUserIds = $this->db->executeQuery(
                    "SELECT mro.user_id FROM msg_reroute_override mro WHERE `msg_route_key` = :routeKey AND `delivery` = :delivery",
                    $parameters)
                    ->fetchAll(PDO::FETCH_COLUMN);
                if ($mutedUserIds) {
                    $userIds = array_diff($userIds, $mutedUserIds);
                }
            }
            return $userIds;
        }

        // Recursive composite case:
        $pb = $this->simpleSegmentMapper->getPredicateBuilder()
                                        ->equals(Mapper::FIELD_PARENT_ID, $audienceSegment->getId());

        $aggregateUsers = [];
        $firstRow       = true;
        foreach ($this->simpleSegmentMapper->findByPredicates($pb) as $childSegment) {
            $userIds = $this->findUsersForAudienceSegment($childSegment, $excludeMuted);
            if ($firstRow) {
                $aggregateUsers = $userIds;
                $firstRow       = false;
            } else {
                $aggregateUsers = \array_intersect($aggregateUsers, $userIds);
            }
        }

        return $this->applyRelationshipFilter($aggregateUsers, $audienceSegment->getRelationship());
    }


    /**
     * Returns the set of users who are related to the specified users by the given relationship filter
     *
     * @param array  $userIds
     * @param string $relationship ['self', 'parents_of', 'teachers_of']
     * @return int[]
     * @throws UnderflowException
     * @throws InvalidColumnException
     * @throws UnexpectedValueException If an unrecognised relationship modifier is encountered
     */
    private function applyRelationshipFilter(array $userIds, string $relationship): array
    {
        if (!\count($userIds)) {
            return [];
        }
        if (Model::RELATIONSHIP_SELF === $relationship) {
            return $userIds;
        }

        switch ($relationship) {
            case Model::RELATIONSHIP_PARENTS_OF:
                return $this->relationshipFilterer->getParents($userIds);
            case Model::RELATIONSHIP_TEACHERS_OF:
                return $this->relationshipFilterer->getTeachersAndInstructors($userIds);
            default:
                throw new UnexpectedValueException("Unrecognised relationship modifier: {$relationship}");
        }
    }


    /**
     * Returns a set of userIds that match a given entity type and identifier
     *
     * @param string      $entityType
     * @param null|string $entityValue
     * @return int[]
     */
    private function getUserIds(string $entityType, ?string $entityValue): array
    {
       return [];
    }


    /**
     * @inheritDoc
     * @throws Database\Exception
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws LogicException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    public function isUserInAudience(User $user, News\Model $article): bool
    {
        // TODO This is not performant. We already have a hydrated user and article.
        // TODO We should first examine the user and article to see if we can short-circuit a result, before hitting the DB.
        return in_array($user->getId(), $this->findUsersForArticle($article), false);
    }
}
