<?php
/**
 * Folder
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Segment\ISearch;
use Schoolbox\Comms\News\Audience\Segment\ITranslate;

class Folder implements ISearch, ITranslate
{

    /**
     * Gets an sql fragment for the ISearch instance
     *
     * Folders must be in one of these two possibilities:
     * Have a class code, or be a Group
     *
     * Anything else is rolled over and shouldn't appear as a search result
     *
     * @return string
     */
    public function getSearchSql(): string
    {
        return "
            (SELECT folder.id, 
                CONCAT(folder.name,  
                    CASE
                    WHEN COUNT(folder_code.code) > 0
                    THEN CONCAT(' (', GROUP_CONCAT(folder_code.code SEPARATOR ', '), ')')
                    ELSE ''
                    END) AS `name`,
                'folder' AS entity_type
            FROM folder
            LEFT JOIN folder_code ON folder.id = folder_code.folder_id AND folder_code.deleted_at IS NULL
            WHERE (
                folder.id IN (SELECT folder_id FROM folder_code WHERE deleted_at IS NULL)
                OR
                folder.id IN (SELECT cid FROM userGroups)
            )
            AND (folder.name LIKE :keyword OR folder_code.code LIKE :keyword)
            AND folder.deleted_at IS NULL
            GROUP BY folder.id
            ORDER BY folder.name)
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
            (SELECT cna1.*, f.name 
            FROM 
            (SELECT folder.id,
                CONCAT(name,  
                CASE
                WHEN COUNT(code) > 0
                THEN CONCAT(' (', GROUP_CONCAT(code SEPARATOR ', '), ')')
                ELSE ''
                END) AS name
            FROM folder
            LEFT JOIN folder_code ON folder.id = folder_code.folder_id AND folder_code.deleted_at IS NULL
            WHERE folder.id IN 
                (SELECT DISTINCT entity_value
                FROM comms_news_audience cna2
                WHERE entity_type = 'folder'
                AND cna2.comms_news_article_id = :articleId)
            AND folder.deleted_at IS NULL
            GROUP BY folder.id) AS f 
            INNER JOIN comms_news_audience cna1 ON cna1.entity_value = f.id AND entity_type = 'folder')
        ";
    }
}