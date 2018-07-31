<?php
/**
 * User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Search;

class User implements ISearch
{

    /**
     * Gets an sql fragment for the ISearch instance
     *
     * @return string
     */
    public function getSearchSql(): string
    {
        return "
            (SELECT id, CONCAT(firstname, ' ', lastname) AS name, 'user' AS entity_type
            FROM `user`
            WHERE firstname LIKE :keyword OR lastname LIKE :keyword
            AND deleted_at IS NULL)
        ";
    }
}