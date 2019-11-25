<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use PDO;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;

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


    /**
     * @inheritdoc
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        $entityType = self::getEntityType();
        return "
            SELECT DISTINCT cna.entity_type, cna.entity_value, pastoral_group.`name` COLLATE utf8mb4_general_ci AS `name`
            FROM comms_news_audience cna
            INNER JOIN pastoral_group ON entity_type = '{$entityType}' AND cna.entity_value = pastoral_group.id
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
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
}
