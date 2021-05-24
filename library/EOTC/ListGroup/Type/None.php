<?php

/**
 * None
 *
 * When an event does not fall into any Event List Grouping
 * such as when it's finally approved, rejected
 *
 * This also is used when an event enters RAMS Approved state
 * but not needing RAMS (perhaps when an author changes the RAMS
 * requirement while it's already in RAMS Approved state)
 *
 * Whatever the case, if you end up here, the event won't appear
 * on the Review screen
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC\ListGroup\Type;

use EOTC\EventStatusMapper;

class None
{

    /**
     * Next status id
     *
     * @var int
     */
    protected $nextStatusId = EventStatusMapper::STATUS_REJECTED;

    /**
     * Determine where to go next
     *
     * @throws \RuntimeException
     */
    public function determine()
    {
        throw new \RuntimeException('Cannot assign event to a list group.');
    }
}
