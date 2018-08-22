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
     * Gets an sql fragment for the ISearch instance
     *
     * @return string
     */
    public function getSearchSql(): string
    {
        return "
            SELECT id, CONCAT(firstname, ' ', lastname) AS name, '".Model::ENTITY_TYPE_USER."' AS entity_type
            FROM `user`
            WHERE (firstname LIKE :keyword OR lastname LIKE :keyword)
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
            SELECT cna.*, CONCAT(firstname, ' ', lastname) AS `name`
            FROM comms_news_audience cna
            INNER JOIN `user` ON entity_type = '".Model::ENTITY_TYPE_USER."' AND entity_value = `user`.id
            WHERE comms_news_article_id = :articleId
        ";
    }
}
