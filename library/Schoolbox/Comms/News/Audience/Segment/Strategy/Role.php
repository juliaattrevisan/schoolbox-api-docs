<?php
/**
 * Role
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Comms\News\Audience\Segment\ISearch;

class Role implements ISearch
{

    /**
     * Gets an sql fragment for the ISearch instance
     *
     * @return string
     */
    public function getSearchSql(): string
    {
        return "
            (SELECT id, name, 'role' AS entity_type
            FROM groups
            WHERE `name` LIKE :keyword
            AND deleted_at IS NULL)
        ";
    }
}