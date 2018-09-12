<?php
/**
 * House
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Audience\Segment\ISearch;
use Schoolbox\Comms\News\Audience\Segment\ITranslate;

class House implements ISearch, ITranslate
{

    /**
     * @inheritdoc
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        $entityType    = Model::ENTITY_TYPE_SCHOOL_HOUSE;
        $keywordFilter = null === $searchPlaceholder ? '' : "WHERE house LIKE :{$searchPlaceholder}";

        return "
            SELECT id, house AS name, '{$entityType}' AS entity_type
            FROM house
            {$keywordFilter}
        ";
    }


    /**
     * @inheritdoc
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        $entityType = Model::ENTITY_TYPE_SCHOOL_HOUSE;
        return "
            SELECT DISTINCT cna.entity_type, cna.entity_value, house.house AS `name`
            FROM comms_news_audience cna
            INNER JOIN house ON cna.entity_type = '{$entityType}' AND cna.entity_value = house.id
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
    }
}
