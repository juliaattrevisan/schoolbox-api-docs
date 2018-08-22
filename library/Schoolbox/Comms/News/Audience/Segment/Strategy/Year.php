<?php
/**
 * Year
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Audience\Segment\ISearch;
use Schoolbox\Comms\News\Audience\Segment\ITranslate;

class Year implements ISearch, ITranslate
{

    /**
     * Gets an sql fragment for the ISearch instance
     *
     * @return string
     */
    public function getSearchSql(): string
    {
        $entityType = Model::ENTITY_TYPE_YEAR;

        // If user.year is numeric, prefix 'Year ' to read like 'Year 9'
        return "
            SELECT
              year AS id,
              IF(year REGEXP '^[0-9]+$', CONCAT('Year ', year), year) AS name,
              '{$entityType}' AS entity_type
            FROM `user`
            WHERE year LIKE :keyword
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
            SELECT cna.*,
                CASE
                WHEN CONCAT('', 1*`user`.year) = `user`.year
                THEN CONCAT('Year ', `user`.year)
                ELSE `user`.year
                END
            AS `name`
            FROM comms_news_audience cna
            INNER JOIN `user` ON entity_type = '".Model::ENTITY_TYPE_YEAR."' AND entity_value = `user`.year
            WHERE comms_news_article_id = :articleId
        ";
    }
}
