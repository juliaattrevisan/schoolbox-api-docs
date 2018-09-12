<?php
/**
 * PastoralGroup
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Audience\Segment\ISearch;
use Schoolbox\Comms\News\Audience\Segment\ITranslate;

class PastoralGroup implements ISearch, ITranslate
{

    /**
     * @inheritdoc
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        $entityType    = Model::ENTITY_TYPE_PASTORAL_GROUP;
        $keywordFilter = null === $searchPlaceholder ? '' : "AND `name` LIKE :{$searchPlaceholder}";

        // Note that we need to collate the output so the result set can be UNIONed with other result sets
        return "
            SELECT id, name COLLATE utf8mb4_general_ci, '{$entityType}' AS entity_type
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
        $entityType = Model::ENTITY_TYPE_PASTORAL_GROUP;
        return "
            SELECT DISTINCT cna.entity_type, cna.entity_value, pastoral_group.`name` COLLATE utf8mb4_general_ci AS `name`
            FROM comms_news_audience cna
            INNER JOIN pastoral_group ON entity_type = '{$entityType}' AND cna.entity_value = pastoral_group.id
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
    }
}
