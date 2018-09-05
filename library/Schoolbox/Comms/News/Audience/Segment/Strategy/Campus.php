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
     * Translate this ITranslate instance from an entity_type to a human-readable version
     *
     * @return string
     */
    public function getTranslateSql(): string
    {
        return "
            SELECT cna.*, luCampus.`name` AS `name`
            FROM comms_news_audience cna
            INNER JOIN luCampus ON entity_type = '".Model::ENTITY_TYPE_CAMPUS."' AND entity_value = luCampus.id
            WHERE comms_news_article_id = :articleId
        ";
    }
}
