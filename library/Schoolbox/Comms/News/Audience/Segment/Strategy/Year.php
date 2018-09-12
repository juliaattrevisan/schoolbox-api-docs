<?php
/**
 * Year
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Audience\Segment\ISearch;
use Schoolbox\Comms\News\Audience\Segment\ITranslate;

class Year implements ISearch, ITranslate
{

    /**
     * @inheritdoc
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        $entityType    = Model::ENTITY_TYPE_YEAR;
        $keywordFilter = null === $searchPlaceholder ? '' : "AND year LIKE :{$searchPlaceholder}";

        // If user.year is numeric, prefix 'Year ' to read like 'Year 9'
        return "
            SELECT DISTINCT
              year AS id,
              IF(year REGEXP '^[0-9]+$', CONCAT('Year ', year), year) AS name,
              '{$entityType}' AS entity_type
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
        $entityType = Model::ENTITY_TYPE_YEAR;
        return "
            SELECT DISTINCT cna.entity_type, cna.entity_value, IF(year REGEXP '^[0-9]+$', CONCAT('Year ', year), year) AS name
            FROM comms_news_audience cna
            INNER JOIN `user` ON cna.entity_type = '{$entityType}' AND cna.entity_value = `user`.year
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
    }
}
