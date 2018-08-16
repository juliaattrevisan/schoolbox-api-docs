<?php
/**
 * PastoralGroup
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Segment\ISearch;
use Schoolbox\Comms\News\Audience\Segment\ITranslate;

class PastoralGroup implements ISearch, ITranslate
{

    /**
     * Gets an sql fragment for the ISearch instance
     *
     * @return string
     */
    public function getSearchSql(): string
    {
        return "
            SELECT id, name COLLATE utf8mb4_general_ci, 'pastoral_group' AS entity_type 
            FROM `pastoral_group`
            WHERE `name` LIKE :keyword
            AND deleted_at IS NULL
        ";
    }


    /**
     * Translate this ITranslate instance from an entity_type to a human-readable version
     *
     * @return string
     */
    public function getTranslateSql(): string
    {
        return "
            SELECT cna.*, pastoral_group.`name` COLLATE utf8mb4_general_ci AS `name`
            FROM comms_news_audience cna
            INNER JOIN pastoral_group ON entity_type = 'pastoral_group' AND entity_value = pastoral_group.id
            WHERE comms_news_article_id = :articleId
        ";
    }
}
