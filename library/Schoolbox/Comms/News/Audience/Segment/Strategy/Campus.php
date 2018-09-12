<?php
/**
 * Campus
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Audience\Segment\ISearch;
use Schoolbox\Comms\News\Audience\Segment\ITranslate;

class Campus implements ISearch, ITranslate
{

    /**
     * @inheritdoc
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        $entityType    = Model::ENTITY_TYPE_CAMPUS;
        $keywordFilter = null === $searchPlaceholder ? '' : "WHERE `name` LIKE :{$searchPlaceholder}";

        return "
            SELECT id, name, '{$entityType}' AS entity_type
            FROM luCampus
            {$keywordFilter}
        ";
    }


    /**
     * @inheritdoc
     */
    public function getTranslateSql(string $segmentIdsPlaceholder): string
    {
        $entityType = Model::ENTITY_TYPE_CAMPUS;
        return "
            SELECT DISTINCT cna.entity_type, cna.entity_value, luCampus.name
            FROM comms_news_audience cna
            INNER JOIN luCampus ON cna.entity_type = '{$entityType}' AND cna.entity_value = luCampus.id
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
    }
}
