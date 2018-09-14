<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;


class SchoolHouse extends Base
{
    public const ENTITY_TYPE = 'school_house';

    /**
     * @inheritdoc
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        $keywordFilter = null === $searchPlaceholder ? '' : "WHERE house LIKE :{$searchPlaceholder}";

        return "
            SELECT id, house AS name, '{$this->getEntityType()}' AS entity_type
            FROM house
            {$keywordFilter}
        ";
    }


    /**
     * @inheritdoc
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        return "
            SELECT DISTINCT cna.entity_type, cna.entity_value, house.house AS `name`
            FROM comms_news_audience cna
            INNER JOIN house ON cna.entity_type = '{$this->getEntityType()}' AND cna.entity_value = house.id
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
    }

    /**
     * @inheritdoc
     */
    public function canTargetNullEntityValue(): bool
    {
        // User's may not have a school house
        return true;
    }
}
