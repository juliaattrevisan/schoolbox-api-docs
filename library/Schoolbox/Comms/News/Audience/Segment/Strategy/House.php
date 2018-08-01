<?php
/**
 * House
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Segment\ISearch;
use Schoolbox\Comms\News\Audience\Segment\ITranslate;

class House implements ISearch, ITranslate
{

    /**
     * Gets an sql fragment for the ISearch instance
     *
     * @return string
     */
    public function getSearchSql(): string
    {
        return "
            (SELECT id, house AS name, 'school_house' AS entity_type
            FROM house
            WHERE house LIKE :keyword)
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
            (SELECT cna.*, house.house AS `name`
            FROM schoolbox.comms_news_audience cna
            INNER JOIN house ON entity_type = 'school_house' AND entity_value = house.id
            WHERE comms_news_article_id = :articleId)
        ";
    }
}
