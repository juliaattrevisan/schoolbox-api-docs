<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;

/**
 * Class Code
 *
 * Return a string representation of a (year group, subject code, class) tuple
 * This is a format we adopted for tass more-or-less across the board
 *
 * ----
 * From Tammy, 8/10/22
 * Hi Steve
 *
 * A school will either be ‘Unitised’ or ‘Non-Unitised’ in their timetable setup.
 *
 * A non-unitised school will always have ‘year_grp’ entered for every reference for
 * a subject including in the timetable.
 *
 * A unitised school will never have ‘year_grp’ entered. Instead, if the subject is
 * specific for one year level then ‘subtab_year_grp’ will be entered.
 *
 * If the subject is a true unitised subject and is not specific for one year level
 * (eg an elective subject in senior) then no year group will be defined at all
 * and ‘subtab_year_grp’ will be blank.
 * ----
 *
 * SUBTAB_YEAR_GROUP literally means "the Year Group that was specified in the
 * Subject Tab of the TASS ui". It's named after the screen on which you see it.
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable
 */
class Code
{

    /**
     * Encodes three pieces of data into a hyphen-separated string
     * Year Group, Subject Code, Class are concatenated into the format
     * "yearGroup-subjectCode-class", such as:
     *
     * 10-ABC123-2
     *
     * Note that pieces can be null (empty), in particular, the Year Group.
     * It still obeys the same format, and could end up looking like this:
     *
     * -ABC123-2
     *
     * I haven't seen other combinations but I can't trust tass really, so
     * make it as left-leaning as possible and include worst possible case --
     *
     * @param string $yearGroup
     * @param string $subjectCode
     * @param string $class
     * @return string
     */
    public function encode(string $yearGroup, string $subjectCode, string $class): string
    {
        return "{$yearGroup}-{$subjectCode}-{$class}";
    }
}
