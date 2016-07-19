<?php
/**
 * ISnapshot
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Snapshot;


interface ISnapshot
{
    /**
     * Execute snapshot
     *
     * @return void
     */
    public function snapshot();
}