<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\EntityType\Role;

use Schoolbox\Comms\News\Audience\Segment\EntityType\StrategyBase;
use Schoolbox\User;

class Strategy extends StrategyBase
{
    public const ENTITY_TYPE = 'role';

    /**
     * @inheritdoc
     */
    public function canBeTargetedByUser(User $user): bool
    {
        if (parent::canBeTargetedByUser($user)) {
            return true;
        }
        return $this->getStaffCanPost() && $user->getRole()->isStaff();
    }

    /**
     * @inheritdoc
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        $keywordFilter = null === $searchPlaceholder ? '' : "AND `name` LIKE :{$searchPlaceholder}";

        return "
            SELECT id, name, '{$this->getEntityType()}' AS entity_type
            FROM groups
            WHERE deleted_at IS NULL
            {$keywordFilter}
        ";
    }


    /**
     * @inheritdoc
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        return "
            SELECT DISTINCT cna.entity_type, cna.entity_value, groups.name AS `name`
            FROM comms_news_audience cna
            INNER JOIN groups ON cna.entity_type = '{$this->getEntityType()}' AND cna.entity_value = groups.id
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
    }
}
