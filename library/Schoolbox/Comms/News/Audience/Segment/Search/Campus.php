<?php
/**
 * Campus
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment\Search;

class Campus implements ISearch
{

    /**
     * Gets an sql fragment for the ISearch instance
     *
     * @return string
     */
    public function getSearchSql(): string
    {
        return "
            (SELECT id, name, 'campus' AS entity_type
            FROM luCampus
            WHERE `name` LIKE :keyword)
        ";
    }
}