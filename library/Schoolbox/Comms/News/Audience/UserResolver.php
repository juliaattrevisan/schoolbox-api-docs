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
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use PDO;
use Schoolbox\User;
use UnderflowException;
use UnexpectedValueException;

use function array_diff;
use function array_intersect;
use function array_merge;
use function array_unique;
use function count;
use function implode;
use function in_array;

/**
 * Responsible for:
 * * Given an article, finding the set of users who are in its audience.
 */
class UserResolver implements IUserResolver
{
    /** @var Database\Connector\IConnector */
    private $db;

    /** @var RelationshipFilterer */
    private $relationshipFilterer;

    /** @var Segment\StrategyFactory */
    private $strategyFactory;


    /**
     * @param Database\Connector\IConnector $db
     * @param RelationshipFilterer          $relationshipFilterer
     * @param Segment\StrategyFactory       $strategyFactory
     */
    public function __construct(
        Database\Connector\IConnector $db,
        RelationshipFilterer $relationshipFilterer,
        Segment\StrategyFactory $strategyFactory
    ) {
        $this->db                   = $db;
        $this->relationshipFilterer = $relationshipFilterer;
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
     * @throws Exception
     */
    public function findUsersForArticle(News\Model $article, bool $excludeMuted = false): array
    {
        $bricks = (new BrickManager($this->db))->generateBricks($article->getId());

        return $this->findUsersForBricks($bricks, $excludeMuted);
    }


    /**
     * Returns the ids of users who match the specified audience bricks using UNION
     *
     * @param GenericCollection $bricks
     * @param bool              $excludeMuted
     * @return array
     * @throws Database\Exception
     * @throws DefinitionException
     * @throws Exception
     * @throws Exception\InsufficientHydrationException
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws News\Exceptions\UnknownEntityTypeException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    private function findUsersForBricks(GenericCollection $bricks, bool $excludeMuted): array
    {
        if (!$bricks->count()) {
            return [];
        }

        $subQueries   = [];
        $allParams    = [];
        $uniquePrefix = 0;
        foreach ($bricks as $brick) {
            foreach ($brick->getRelationships() as $relationship) {
                [
                    $sql,
                    $params,
                ] = $this->buildDimensionsQuery(++$uniquePrefix, $relationship, $brick->getDimensions(), $excludeMuted);
                if ($sql) {
                    $subQueries[] = $sql;
                    $allParams[]  = $params;
                }
            }
        }
        // We will have no subQueries if we detected that all bricks could not resolve to any users.
        if (!count($subQueries)) {
            return [];
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
     * Returns whether array contains any folder dimensions
     *
     * @param Dimension[] $dimensions
     * @return bool
     */
    private function hasFolderDimension(array $dimensions): bool
    {
        foreach ($dimensions as $dimension) {
            if ($dimension->getType() === Segment\Strategy\Folder::getEntityType()) {
                return true;
            }
        }
        return false;
    }


    /**
     * Returns a SQL query + params where the query returns two columns:
     * * 'id'           - representing a user id.
     * * 'relationship' - a relationship modifier that needs to be applied to users ids
     *
     * @param int         $subQueryId
     * @param string      $relationship
     * @param Dimension[] $dimensions
     * @param bool        $excludeMuted
     * @return array
     * @throws Database\Exception
     * @throws DefinitionException
     * @throws Exception
     * @throws Exception\InsufficientHydrationException
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws News\Exceptions\UnknownEntityTypeException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    private function buildDimensionsQuery(int $subQueryId, string $relationship, array $dimensions, bool $excludeMuted): array
    {
        $userTableAlias = "user_{$subQueryId}";

        // Special case if:
        // * excluding muted audiences,
        // * dimensions contain folder segments, and
        // * dimensions not targeted to self.
        // We must apply the relationship modifier before removing muted users
        if ($excludeMuted && $relationship !== Model::RELATIONSHIP_SELF && $this->hasFolderDimension($dimensions)) {
            return $this->buildMutedRelatedDimensionsQuery($userTableAlias, $subQueryId, $relationship, $dimensions, $excludeMuted);
        }

        return $this->buildNormalCaseDimensionsQuery($userTableAlias, $subQueryId, $relationship, $dimensions, $excludeMuted);
    }


    /**
     * Returns a SQL query + params where the query returns a two columns:
     * * 'id'           - representing a user id.
     * * 'relationship' - a relationship modifier that needs to be applied to users ids
     *
     * This handles the most common cases. It should not be called if:
     * * $relationship !== 'self'
     * * $excludeMuted === TRUE, and
     * * $dimensions contains a 'folder' dimension
     *
     * @param string $userTableAlias
     * @param int    $subQueryId
     * @param string $relationship
     * @param array  $dimensions
     * @param bool   $excludeMuted
     * @return array
     * @throws InvalidColumnException
     * @throws News\Exceptions\UnknownEntityTypeException
     * @throws InvalidArgumentException
     */
    private function buildNormalCaseDimensionsQuery(string $userTableAlias, int $subQueryId, string $relationship, array $dimensions, bool $excludeMuted): array
    {
        if ($excludeMuted && $relationship !== Model::RELATIONSHIP_SELF && $this->hasFolderDimension($dimensions)) {
            throw new InvalidArgumentException("This method should not be called to fetch muted + related folder members");
        }

        $joins = [];
        $pb    = (new Predicate\Builder(new Quoter(), ''))->isNull("{$userTableAlias}.deleted_at");

        foreach ($dimensions as $idx => $dimension) {
            $strategy = $this->strategyFactory->makeSimpleStrategy($dimension->getType());
            $data     = $strategy->getUserResolutionSql($userTableAlias, "_{$subQueryId}_{$idx}", $dimension->getValues(), $excludeMuted);
            $joins[]  = $data->getJoin();
            $pb->all($data->getPb());
        }

        $joinClause  = implode("\n", array_merge($joins));
        $sql         = "
            SELECT {$userTableAlias}.id, '{$relationship}' relationship
            FROM user {$userTableAlias}
            LEFT JOIN lu_year_level lyl ON {$userTableAlias}.year_id = lyl.id
            {$joinClause}
            WHERE {$pb->getWhereIntersect()}
        ";

        return [$sql, $pb->getParams()];
    }


    /**
     * Returns a SQL query + params where the query returns two columns:
     * * 'id'           - representing a user id.
     * * 'relationship' - a relationship modifier that needs to be applied to users ids
     *
     * This should only be called in the case where:
     * * $relationship !== 'self',
     * * $dimensions contains folder dimension/s
     *
     * We need to resolve users, apply relationship modifier, then remove muted users, then INTERSECT dimensions users
     *
     * @param string $userTableAlias
     * @param int    $subQueryId
     * @param string $relationship A non-self modifier
     * @param array  $dimensions
     * @param bool   $excludeMuted
     * @return array
     * @throws Database\Exception
     * @throws DefinitionException
     */
    private function buildMutedRelatedDimensionsQuery(
        string $userTableAlias,
        int $subQueryId,
        string $relationship,
        array $dimensions,
        bool $excludeMuted
    ): array {
        if ($relationship === Model::RELATIONSHIP_SELF || !$this->hasFolderDimension($dimensions)) {
            throw new InvalidArgumentException("Params do not contain a folder dimension with non-self relationship modifier");
        }

        // We can handle all non-folder dimensions as an aggregate
        /** @var Dimension[] $folderDimensions */
        $folderDimensions = [];
        /** @var Dimension[] $nonFolderDimensions */
        $nonFolderDimensions = [];
        foreach ($dimensions as $idx => $dimension) {
            if ($dimension->getType() === Segment\Strategy\Folder::getEntityType()) {
                $folderDimensions[$idx] = $dimension;
            } else {
                $nonFolderDimensions[$idx] = $dimension;
            }
        }
        [
            $sql,
            $params,
        ] = $this->buildNormalCaseDimensionsQuery($userTableAlias, $subQueryId, Model::RELATIONSHIP_SELF, $nonFolderDimensions, false);
        $users = [];
        $query = $this->db->executeQuery($sql, $params);
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $users[] = $row['id'];
        }
        $relatedUsers   = [];
        $relatedUsers[] = $this->applyRelationshipFilter($users, $relationship);

        // Folder dimensions must be handled per dimension
        foreach ($folderDimensions as $folderDimension) {
            // Need to handle each folder dimension individually
            $relatedUsers[] = $this->getNonMutedFolderRelatedUsers($relationship, $folderDimension->getValues(), $excludeMuted);
        }

        $relatedUsers = array_intersect(...$relatedUsers);
        if (!count($relatedUsers)) {
            return [null, []];
        }

        $pb  = (new Predicate\Builder($this->db->getQuoter(), $userTableAlias))
            ->setParamPrefix("_{$subQueryId}_x")
            ->in("{$userTableAlias}.id", $relatedUsers)
            ->isNull("{$userTableAlias}.deleted_at");

        $sql = "SELECT {$userTableAlias}.id, 'self' relationship FROM user {$userTableAlias} WHERE {$pb->getWhereIntersect()}";

        return [$sql, $pb->getParams()];
    }


    /**
     * Fetch users for a set of folders, respecting per-folder mutes.
     *
     * eg, Consider folders A,B and users 1,2,3
     * * user1 mutes no folders    => user1 is returned
     * * user2 mutes folder A only => user2 is returned because entity values or combined using UNION
     * * user3 mutes folder A,B    => user3 is not returned
     *
     * Mutes are always derived from NNIAN folders, as these are the folders displayed in user settings.
     *
     * @param string          $relationship
     * @param null[]|string[] $folderIds
     * @return array
     */
    private function getNonMutedFolderRelatedUsers(string $relationship, array $folderIds, bool $excludeMuted): array
    {
        /** @var Segment\Strategy\Folder $strategy */
        $strategy        = $this->strategyFactory->makeSimpleStrategy(Segment\Strategy\Folder::getEntityType());
        try {
            $nnianFolderIds = $strategy->findNnianFolderIds($folderIds);
            $mutedFolders   = $strategy->getMutedUsersPerFolder($nnianFolderIds);
        } catch (Exception $e) {
            // rather than stopping notification, report the issue and proceed
            trigger_error('Failed to get muted folders for ' . implode(',', $folderIds) . ': ' . $e->getMessage(), E_USER_WARNING);
            $mutedFolders = [];
        }
        $allRelatedUsers = [];
        foreach ($folderIds as $folderId) {
            if ($excludeMuted && $mutedFolders[$folderId] && $mutedFolders[$folderId] === Segment\Strategy\Folder::MUTED) {
                // if notification is disabled, skip this folder
                continue;
            }
            $userResSql        = $strategy->getUserResolutionSql('user', "", [$folderId], false);
            $join              = $userResSql->getJoin();
            $pb                = $userResSql->getPb();
            $sql               = "SELECT id FROM user {$join} WHERE {$pb->getWhereIntersect()} AND deleted_at IS NULL";
            $userIds           = $this->db->executeQuery($sql, $pb->getParams())->fetchAll(PDO::FETCH_COLUMN);
            $relatedUsers      = $this->applyRelationshipFilter($userIds, $relationship);
            $allRelatedUsers[] = array_diff($relatedUsers, $mutedFolders[$folderId] ?? []);
        }

        return array_unique(array_merge(...$allRelatedUsers));
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
        if (!count($userIds)) {
            return [];
        }
        if (Model::RELATIONSHIP_SELF === $relationship) {
            return $userIds;
        }

        switch ($relationship) {
            case Model::RELATIONSHIP_PARENTS_OF:
                return $this->relationshipFilterer->getParents($userIds);
            case Model::RELATIONSHIP_TEACHERS_OF:
                return $this->relationshipFilterer->getTeachers($userIds);
            default:
                throw new UnexpectedValueException("Unrecognised relationship modifier: {$relationship}");
        }
    }


    /**
     * @inheritDoc
     * @throws Database\Exception
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws LogicException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws Exception
     */
    public function isUserInAudience(User $user, News\Model $article): bool
    {
        // Convert segments to bricks for easier analysis
        $bricks = (new BrickManager($this->db))->generateBricksFromAudience($article->getAudience());

        // Keep track of bricks we are unable to derive a conclusion for using currently available model graph
        $unknownBricks = Brick::createCollection();

        /* if(user matches any brick)
         *   then user matches audience
         * elseif(any brick match is unknown)
         *   then we need to hit the DB to determine if user matches the unknown bricks
         * else(user matches no bricks)
         *   then user is not in audience
         */
        foreach ($bricks as $brick) {
            foreach ($brick->getRelationships() as $relationship) {
                $isMatch = $this->doesUserMatchDimensions($user, $brick->getDimensions(), $relationship);
                if ($isMatch === true) {
                    return true;
                }
                if ($isMatch === null) {
                    // No need to collapse bricks here, they are already compressed.
                    $unknownBricks->add(new Brick($brick->getDimensions(), $relationship));
                }
            }
        }

        // Resolve audience for indeterminate bricks, check if user is found
        return in_array($user->getId(), $this->findUsersForBricks($unknownBricks, false), false);
    }


    /**
     * Returns whether a user matches a set of dimensions and specified relationship
     *
     * * If a user does not match any dimension, then they do not match the intersection of those dimensions.
     * * If any dimension is unknown, we cannot conclude they will match
     * * If all dimensions match, then user matches the set
     *
     * @param User        $user
     * @param Dimension[] $dimensions
     * @param string      $relationship
     * @return bool|null
     * @throws News\Exceptions\UnknownEntityTypeException
     */
    private function doesUserMatchDimensions(User $user, array $dimensions, string $relationship): ?bool
    {
        $hasUnknown = false;
        foreach ($dimensions as $dimension) {
            $matchesDimension = $this->doesUserMatchDimension($user, $dimension, $relationship);
            if ($matchesDimension === false) {
                return false;
            }
            if ($matchesDimension === null) {
                $hasUnknown = true;
            }
        }
        return $hasUnknown ? null : true;
    }


    /**
     * Returns whether a user is in a Dimension's audience.
     *
     * Returns NULL if unknown.
     *
     * @param User      $user
     * @param Dimension $dimension
     * @param string    $relationship
     * @return bool|null
     * @throws News\Exceptions\UnknownEntityTypeException
     */
    private function doesUserMatchDimension(User $user, Dimension $dimension, string $relationship): ?bool
    {
        $strategy = $this->strategyFactory->makeSimpleStrategy($dimension->getType());
        return $strategy->isUserInAudienceValues($user, $dimension->getValues(), $relationship);
    }
}
