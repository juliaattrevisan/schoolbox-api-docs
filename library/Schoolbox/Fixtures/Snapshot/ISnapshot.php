<?php

/**
 * ISnapshot
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Snapshot;

interface ISnapshot
{
    /**
     * Execute snapshot
     *
     * @return void
     */
    public function snapshot();
}
