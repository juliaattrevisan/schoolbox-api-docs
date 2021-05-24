<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar;

use Schoolbox\Collection;

/**
 * Class EventLevelCollection
 *
 * A set of options that correspond to possible Event Levels
 * Shown on the Event Modify screen
 *
 * If there are options for the Group and School possibilities, it'll present them as options,
 * otherwise they're excluded so you don't select a meaningless option
 *
 * The default set is [Personal, Group, School] corresponding to their consts
 *
 * @package Schoolbox\Calendar
 */
class EventLevelCollection extends Collection
{

    /**
     * Ram in hardcoded values
     *
     * @param Collection $groupList
     * @param Collection $campusList
     */
    public function __construct(Collection $groupList, $campusList)
    {
        $this->add(new EventLevel(Event::ACCESS_PERSONAL, 'Personal'));

        if ($groupList->count()) {
            $this->add(new EventLevel(Event::ACCESS_GROUP, 'Group'));
        }

        if ($campusList->count()) {
            $this->add(new EventLevel(Event::ACCESS_CAMPUS, 'School'));
        }
    }
}
