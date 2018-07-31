<?php
/**
 * ISearch
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Audience\Segment;

interface ISearch
{

    /**
     * Gets an sql fragment for the ISearch instance
     *
     * @return string
     */
    public function getSearchSql(): string;
}