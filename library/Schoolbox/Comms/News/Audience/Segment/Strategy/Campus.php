<?php
/**
 * Campus
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Segment\ISearch;
use Schoolbox\Comms\News\Audience\Segment\ITranslate;

class Campus implements ISearch, ITranslate
{

    /**
     * Gets an sql fragment for the ISearch instance
     *
     * @return string
     */
    public function getSearchSql(): string
    {
        return "
            SELECT id, name, 'campus' AS entity_type
            FROM luCampus
            WHERE `name` LIKE :keyword
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
            SELECT cna.*, luCampus.`name` AS `name`
            FROM comms_news_audience cna
            INNER JOIN luCampus ON entity_type = 'campus' AND entity_value = luCampus.id
            WHERE comms_news_article_id = :articleId
        ";
    }
}
