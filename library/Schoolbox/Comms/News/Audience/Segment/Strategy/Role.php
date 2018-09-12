<?php
/**
 * Role
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Audience\Segment\ISearch;
use Schoolbox\Comms\News\Audience\Segment\ITranslate;

class Role implements ISearch, ITranslate
{

    /**
     * @inheritdoc
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        $entityType    = Model::ENTITY_TYPE_ROLE;
        $keywordFilter = null === $searchPlaceholder ? '' : "AND `name` LIKE :{$searchPlaceholder}";

        return "
            SELECT id, name, '{$entityType}' AS entity_type
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
        $entityType = Model::ENTITY_TYPE_ROLE;
        return "
            SELECT DISTINCT cna.entity_type, cna.entity_value, groups.name AS `name`
            FROM comms_news_audience cna
            INNER JOIN groups ON cna.entity_type = '{$entityType}' AND cna.entity_value = groups.id
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
    }
}
