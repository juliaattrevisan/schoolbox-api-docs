<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Quoter\Mysql\Quoter;
use PDO;
use Schoolbox\Comms\News\Audience\Segment\UserResolutionSql;
use Schoolbox\External\Subject;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;

use function array_intersect;
use function count;
use function implode;
use function in_array;

class PastoralGroup extends SimpleStrategy
{

    /** @var IConnector */
    private $db;


    public static function getEntityType(): string
    {
        return 'pastoral_group';
    }


    /**
     * @inheritdoc
     */
    public static function isValidNullEntityValue(): bool
    {
        // A user may not belong to any pastoral groups
        return false;
//        return true; // TODO Turned off as not-yet implemented in front-end so no need to include in query
    }


    /**
     * @inheritdoc
     * @param IConnector $db
     */
    public function __construct(UserAccessMapper $userAccessMapper, Access $access, bool $staffCanPost, bool $moderationRequired, IConnector $db)
    {
        parent::__construct($userAccessMapper, $access, $staffCanPost, $moderationRequired);
        $this->db = $db;
    }


    /**
     * @inheritdoc
     */
    public function getAudiencingSql(User $user, string $searchPlaceholder = null): ?string
    {
        $keywordFilter = null === $searchPlaceholder ? '' : "AND `name` LIKE :{$searchPlaceholder}";

        // Note that we need to collate the output so the result set can be UNIONed with other result sets
        $entityType = self::getEntityType();
        return "
            SELECT id, name COLLATE utf8mb4_general_ci, '{$entityType}' AS entity_type, null as entity_meta
            FROM `pastoral_group`
            WHERE deleted_at IS NULL
            {$keywordFilter}
        ";
    }


    /** @inheritdoc */
    public function getTranslateSql(array $entityValues): Audience\Segment\TranslateSql
    {
        $entityType = self::getEntityType();

        $pb = (new Builder(new Quoter(), 'pastoral_group'))
            ->setParamPrefix($entityType)
            ->in('id', $entityValues);

        $sql = "
            SELECT '{$entityType}' entity_type, id entity_value, name COLLATE utf8mb4_general_ci name
            FROM pastoral_group
            WHERE {$pb->getWhereIntersect()}
        ";
        return new Audience\Segment\TranslateSql($sql, $pb->getParams());
    }


    /**
     * @inheritdoc
     */
    public function getSearchSql(User $user): string
    {
        return '';
    }


    /**
     * @param GenericCollection|User[] $users
     * @return array
     */
    public function getValues(GenericCollection $users): array
    {
        $userIds = $users->getPropertyValues('id');
        if (!\count($userIds)) {
            return [];
        }

        $bindings = Builder::getBindings($userIds, 'user');

        $sql = "
            SELECT group_id
            FROM pastoral_group_member WHERE user_id IN ({$bindings->getPlaceholder()})
            AND deleted_at IS NULL
        ";

        return $this->db->executeQuery($sql)->fetchAll(PDO::FETCH_COLUMN);
    }


    /** @inheritDoc */
    public function getUserResolutionSql(
        string $userTableAlias,
        string $paramPrefix,
        array $entityValues,
        bool $excludeMuted
    ): UserResolutionSql {
        $targetNull = self::isValidNullEntityValue() && in_array(null, $entityValues, true);
        $filtered   = $this->filterOutNullValues($entityValues);
        if (!$targetNull && !count($filtered)) {
            return $this->buildImpossibleUserResolution($userTableAlias, $paramPrefix);
        }

        $pgmAlias = "pgm_$paramPrefix";
        $joinType = $targetNull ? "LEFT" : "";
        $join     = "$joinType JOIN pastoral_group_member $pgmAlias ON $pgmAlias.user_id = $userTableAlias.id";

        $pb = $this->newPb($paramPrefix);
        if ($targetNull) {
            $pb->isNull("$pgmAlias.user_id");
        }
        if (count($filtered)) {
            $pb->in("$pgmAlias.group_id", $filtered);
        }
        $pb = $this->newPb($paramPrefix)->any($pb);

        return new UserResolutionSql($join, $pb);
    }


    /**
     * @inheritDoc
     */
    public function isUserInAudienceValues(User $user, array $entityValues, string $relationship): ?bool
    {
        switch ($relationship) {
            case Audience\Model::RELATIONSHIP_SELF:
                return null;
            case Audience\Model::RELATIONSHIP_PARENTS_OF:
                if (!$user->getRole()->isParent()) {
                    return false;
                }
                return null;
            case Audience\Model::RELATIONSHIP_TEACHERS_OF:
                if (!$user->getRole()->isStaff()) {
                    return false;
                }
                return null;
        }
        return null;
    }
}
