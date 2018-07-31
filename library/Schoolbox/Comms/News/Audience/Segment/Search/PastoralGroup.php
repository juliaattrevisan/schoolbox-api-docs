<?php
/**
 * PastoralGroup
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Search;

class PastoralGroup implements ISearch
{

    /**
     * Gets an sql fragment for the ISearch instance
     *
     * @return string
     */
    public function getSearchSql(): string
    {
        return "
            (SELECT id, name COLLATE utf8mb4_general_ci, 'pastoral_group' AS entity_type 
            FROM `pastoral_group`
            WHERE `name` LIKE :keyword
            AND deleted_at IS NULL)
        ";
    }
}