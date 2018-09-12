<?php
/**
 * User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Audience\Segment\ISearch;
use Schoolbox\Comms\News\Audience\Segment\ITranslate;

class User implements ISearch, ITranslate
{

    /**
     * @inheritdoc
     */
    public function getSearchSql(string $searchPlaceholder = null): string
    {
        $entityType    = Model::ENTITY_TYPE_USER;
        $keywordFilter = null === $searchPlaceholder ? '' : "AND (firstname LIKE :{$searchPlaceholder} OR lastname LIKE :{$searchPlaceholder})";

        return "
            SELECT id, CONCAT(firstname, ' ', lastname) AS name, '{$entityType}' AS entity_type
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
        $entityType = Model::ENTITY_TYPE_CAMPUS;
        return "
            SELECT DISTINCT cna.entity_type, cna.entity_value, CONCAT(firstname, ' ', lastname) AS `name`
            FROM comms_news_audience cna
            INNER JOIN `user` ON cna.entity_type = '{$entityType}' AND cna.entity_value = `user`.id
            WHERE cna.id IN ($segmentIdsPlaceholder)
        ";
    }
}
