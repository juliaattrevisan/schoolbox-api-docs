<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

class User extends Base
{
    public const ENTITY_TYPE = 'user';

    /**
     * @inheritdoc
     */
    public function canBeTargetedByUser(\Schoolbox\User $user): bool
    {
        return $user->getRole()->isStaff();
    }

    /**
     * @inheritdoc
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        $keywordFilter = null === $searchPlaceholder ? '' : "AND (firstname LIKE :{$searchPlaceholder} OR lastname LIKE :{$searchPlaceholder})";

        return "
            SELECT id, CONCAT(firstname, ' ', lastname) AS name, '{$this->getEntityType()}' AS entity_type
            FROM `user`
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
            SELECT DISTINCT cna.entity_type, cna.entity_value, CONCAT(firstname, ' ', lastname) AS `name`
            FROM comms_news_audience cna
            INNER JOIN `user` ON cna.entity_type = '{$this->getEntityType()}' AND cna.entity_value = `user`.id
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
    }
}
