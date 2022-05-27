<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Quoter\Mysql\Quoter;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Audience\Segment\TranslateSql;
use Schoolbox\Comms\News\Audience\Segment\UserResolutionSql;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;

/**
 * Audiencing segment for members of a fixtures team
 */
class FixturesTeam extends SimpleStrategy
{
    /**
     * Is the fixtures module enabled?
     */
    private bool $isFixturesEnabled;

    /**
     * @var IConnector
     */
    private IConnector $db;


    /**
     * @inheritdoc
     * @param bool       $isFixturesEnabled
     * @param IConnector $db
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        Access $access,
        bool $staffCanPost,
        bool $moderationRequired,
        bool $isFixturesEnabled,
        IConnector $db
    ) {
        parent::__construct($userAccessMapper, $access, $staffCanPost, $moderationRequired);
        $this->isFixturesEnabled = $isFixturesEnabled;
        $this->db = $db;
    }


    /**
     * @inheritdoc
     */
    public function canAudienceToEntityType(User $user): bool
    {
        return
            $this->isFixturesEnabled &&
            (
                $user->isSuperuser() ||
                $user->getRole()->isStaff() ||
                $this->hasNewsServicePerm($user, UserAccess::ACCESS_WRITE) ||
                $this->access->getAccessLevel($user, UserAccess::SERVICE_SPORTS_ADMINISTRATOR) >= UserAccess::ACCESS_WRITE
            );
    }


    /**
     * @@inheritdoc
     */
    public function canModerate(User $user, ?string $entityValue): bool
    {
        if (
            $user->isSuperuser() ||
            $this->access->getAccessLevel($user, UserAccess::SERVICE_SPORTS_ADMINISTRATOR) >= UserAccess::ACCESS_WRITE ||
            $this->hasNewsServicePerm($user, UserAccess::ACCESS_WRITE)
        ) {
            return true;
        }

        // No other user can moderate if value not set
        if (null === $entityValue) {
            return false;
        }
        return $user->getRole()->isStaff();
    }


    /**
     * @inheritDoc
     */
    public function getAudiencingSql(User $user, string $searchPlaceholder = null): ?string
    {
        $keywordFilter = null === $searchPlaceholder ? '' : "AND st.`name` LIKE :{$searchPlaceholder}";

        $entityType = self::getEntityType();

        return "
            SELECT
                st.id,
                CONCAT(st.name, ' (', sd.name, ', ', ss.name, ')') AS name,
                '{$entityType}' AS entity_type,
                NULL as entity_meta
            FROM sportsfixtures_team st
            INNER JOIN sportsfixtures_division sd ON st.division_id = sd.id
            INNER JOIN sportsfixtures_sport ss ON sd.sport_id = ss.id
            WHERE st.deleted_at IS NULL
            {$keywordFilter}
        ";
    }


    /**
     * @inheritDoc
     */
    public function getUserResolutionSql(string $userTableAlias, string $paramPrefix, array $entityValues, bool $excludeMuted): UserResolutionSql
    {
        $targetNull = self::isValidNullEntityValue() && in_array(null, $entityValues, true);
        $filtered   = $this->filterOutNullValues($entityValues);

        if (!$targetNull && !count($filtered)) {
            return $this->buildImpossibleUserResolution($userTableAlias, $paramPrefix);
        }

        $teamAlias = "stu_{$paramPrefix}";

        /* This connects up to sql pieces in UserResolver.php
         */
        $join = "
            INNER JOIN sportsfixtures_team_user {$teamAlias}
            ON {$teamAlias}.user_id = {$userTableAlias}.id
        ";

        $pb = $this->newPb($paramPrefix)
            ->in("{$teamAlias}.team_id", $filtered);

        return new UserResolutionSql($join, $pb);
    }


    /**
     * @inheritDoc
     */
    public function getTranslateSql(array $entityValues): TranslateSql
    {
        $entityType = self::getEntityType();

        $pb = (new Builder(new Quoter(), 'sportsfixtures_team'))
            ->setParamPrefix($entityType)
            ->in('id', $entityValues);

        $sql = "
            SELECT '{$entityType}' entity_type, id entity_value, name COLLATE utf8mb4_general_ci name
            FROM sportsfixtures_team
            WHERE {$pb->getWhereIntersect()}
        ";

        return new Audience\Segment\TranslateSql($sql, $pb->getParams());
    }


    /**
     * @inheritDoc
     */
    public function getSearchSql(User $user): string
    {
        return '';
    }


    /**
     * @inheritDoc
     */
    public function getValues(GenericCollection $users): array
    {
        $userIds = $users->getPropertyValues('id');

        if (!\count($userIds)) {
            return [];
        }

        $bindings = Builder::getBindings($userIds, 'user');

        $query = "
            SELECT stu.team_id
            FROM sportsfixtures_team_user stu
            INNER JOIN sportsfixtures_team st ON st.id = stu.team_id
            WHERE user_id IN ({$bindings->getPlaceholder()})
            AND st.deleted_at IS NULL
        ";

        return $this->db->executeQuery($query, $bindings->getParams())->fetchAll(\PDO::FETCH_COLUMN);
    }


    /**
     * @inheritDoc
     */
    public static function getEntityType(): string
    {
        return 'fixturesteam';
    }


    /**
     * @inheritdoc
     */
    public function getValidRelationshipModifiers(string $entityMeta = null): array
    {
        return [Model::RELATIONSHIP_SELF, Model::RELATIONSHIP_PARENTS_OF];
    }


    /**
     * @inheritDoc
     */
    public static function isValidNullEntityValue(): bool
    {
        /* All users must be in a team
         */
        return false;
    }
}
