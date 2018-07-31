<?php
/**
 * House
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Search;

class House implements ISearch
{

    /**
     * Gets an sql fragment for the ISearch instance
     *
     * @return string
     */
    public function getSearchSql(): string
    {
        return "
            (SELECT id, house AS name, 'house' AS entity_type
            FROM house
            WHERE house LIKE :keyword)
        ";
    }
}