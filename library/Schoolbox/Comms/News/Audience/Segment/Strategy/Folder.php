<?php
/**
 * Folder
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Alaress\IDatabase;
use Schoolbox\Comms\News\Audience\Segment\ISearch;

class Folder implements ISearch
{

    /**
     * @var IDatabase
     */
    private $database;

    /**
     * Folder constructor.
     * @param IDatabase $db
     */
    public function __construct(IDatabase $db)
    {
        $this->database = $db;
    }

    /**
     * Gets an sql fragment for the ISearch instance
     *
     * @return string
     */
    public function getSearchSql(): string
    {

        /* Folders must be in one of these two possibilities:
         * Have a class code, or be a Group
         * Anything else is rolled over and shouldn't appear as a search result
         */
        $restrictions = [];

        /* These folders are groups
         */
        $groups = [];

        $statement = $this->database->prepare('
            SELECT DISTINCT cid
            FROM userGroups
        ');

        $statement->execute();

        foreach ($statement as $group) {
            $groups[] = $group['cid'];
        }

        if (\count($groups)) {
            $restrictions[] = 'id IN (' . implode(', ', $groups) . ') ';
        }

        /* These folders have folder codes
         */
        $folderCodes = [];

        $statement = $this->database->prepare('
            SELECT DISTINCT id
            FROM folder f
            INNER JOIN folder_code ON f.id = folder_code.folder_id
        ');

        $statement->execute();

        foreach ($statement as $folder) {
            $folderCodes[] = $folder['id'];
        }

        if (\count($groups)) {
            $restrictions[] = 'id IN (' . implode(', ', $folderCodes) . ') ';
        }

        /* Enforce restrictions
         */
        $where = '';

        if (\count($restrictions)) {
            $where = ' AND (' . implode(' OR ', $restrictions) . ') ';
        }

        return "
            SELECT id, 
                CONCAT(name,  
                    CASE
                    WHEN COUNT(code) > 0
                    THEN CONCAT(' (', GROUP_CONCAT(code SEPARATOR ', '), ')')
                    ELSE ''
                    END) AS name,
                'folder' AS entity_type
            FROM folder
            LEFT JOIN folder_code ON folder.id = folder_code.folder_id AND folder_code.deleted_at IS NULL
            WHERE id IN 
                (SELECT folder.id
                FROM folder
                LEFT JOIN folder_code ON folder.id = folder_code.folder_id AND folder_code.deleted_at IS NULL
                WHERE folder.name LIKE :keyword or folder_code.code LIKE :keyword
                AND folder.deleted_at IS NULL)
            AND folder.deleted_at IS NULL
            {$where}
            GROUP BY folder.id
        ";
    }
}