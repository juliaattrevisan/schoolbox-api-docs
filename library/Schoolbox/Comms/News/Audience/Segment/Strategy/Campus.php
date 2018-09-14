<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

class Campus extends Base
{
    public const ENTITY_TYPE = 'campus';

    /**
     * @inheritdoc
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        $keywordFilter = null === $searchPlaceholder ? '' : "WHERE `name` LIKE :{$searchPlaceholder}";

        return "
            SELECT id, name, '{$this->getEntityType()}' AS entity_type
            FROM luCampus
            {$keywordFilter}
        ";
    }


    /**
     * @inheritdoc
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        return "
            SELECT DISTINCT cna.entity_type, cna.entity_value, luCampus.name
            FROM comms_news_audience cna
            INNER JOIN luCampus ON cna.entity_type = '{$this->getEntityType()}' AND cna.entity_value = luCampus.id
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
    }

    /**
     * @inheritdoc
     */
    public function canTargetNullEntityValue(): bool
    {
        // From a schema level, a user has 0:N campus
        return true;
    }
}
