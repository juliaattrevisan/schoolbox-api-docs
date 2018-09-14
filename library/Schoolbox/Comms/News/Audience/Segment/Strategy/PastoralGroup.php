<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

class PastoralGroup extends Base
{
    public const ENTITY_TYPE = 'pastoral_group';

    /**
     * @inheritdoc
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        $keywordFilter = null === $searchPlaceholder ? '' : "AND `name` LIKE :{$searchPlaceholder}";

        // Note that we need to collate the output so the result set can be UNIONed with other result sets
        return "
            SELECT id, name COLLATE utf8mb4_general_ci, '{$this->getEntityType()}' AS entity_type
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
        return "
            SELECT DISTINCT cna.entity_type, cna.entity_value, pastoral_group.`name` COLLATE utf8mb4_general_ci AS `name`
            FROM comms_news_audience cna
            INNER JOIN pastoral_group ON entity_type = '{$this->getEntityType()}' AND cna.entity_value = pastoral_group.id
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
    }

    /**
     * @inheritdoc
     */
    public function canTargetNullEntityValue(): bool
    {
        // Quite likely a user is not in any pastoral groups
        return true;
    }
}
