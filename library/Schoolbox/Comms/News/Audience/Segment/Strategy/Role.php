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
     * Gets an sql fragment for the ISearch instance
     *
     * @return string
     */
    public function getSearchSql(): string
    {
        return "
            SELECT id, name, '".Model::ENTITY_TYPE_ROLE."' AS entity_type
            FROM groups
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
            SELECT cna.*, groups.name AS `name`
            FROM comms_news_audience cna
            INNER JOIN groups ON entity_type = '".Model::ENTITY_TYPE_ROLE."' AND entity_value = groups.id
            WHERE comms_news_article_id = :articleId
        ";
    }
}
