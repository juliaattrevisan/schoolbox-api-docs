<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

class Year extends Base
{
    public const ENTITY_TYPE = 'year';

    /**
     * @inheritdoc
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        $keywordFilter = null === $searchPlaceholder ? '' : "AND year LIKE :{$searchPlaceholder}";

        // If user.year is numeric, prefix 'Year ' to read like 'Year 9'
        return "
            SELECT DISTINCT
              year AS id,
              IF(year REGEXP '^[0-9]+$', CONCAT('Year ', year), year) AS name,
              '{$this->getEntityType()}' AS entity_type
            FROM `user`
            WHERE deleted_at IS NULL
            AND year IS NOT NULL
            {$keywordFilter}
        ";
    }


    /**
     * @inheritdoc
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        return "
            SELECT DISTINCT cna.entity_type, cna.entity_value, IF(year REGEXP '^[0-9]+$', CONCAT('Year ', year), year) AS name
            FROM comms_news_audience cna
            INNER JOIN `user` ON cna.entity_type = '{$this->getEntityType()}' AND cna.entity_value = `user`.year
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
    }

    /**
     * @inheritdoc
     */
    public function canTargetNullEntityValue(): bool
    {
        // User's may not have a year
        return true;
    }
}
