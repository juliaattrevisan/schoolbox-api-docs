<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable;

use Schoolbox\External\Connector\Kamar\Base\Endpoint\Specification as BaseSpecification;

/**
 * Class Specification
 *
 * This explores an xml output to check whether certain Timetable-related nodes exist
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable
 */
class Specification extends BaseSpecification
{

    /**
     * Timetable nodes
     *
     * @var array
     */
    protected $paths = [
        '/SMSDirectoryData/timetables',
        '/SMSDirectoryData/timetables/timetable',
        '/SMSDirectoryData/timetables/timetable/week',
        '/SMSDirectoryData/timetables/timetable/week/days',
        '/SMSDirectoryData/timetables/timetable/week/days/day',
        '/SMSDirectoryData/timetables/timetable/week/days/day/slots',
        '/SMSDirectoryData/timetables/timetable/week/days/day/slots/subjects',
        '/SMSDirectoryData/timetables/timetable/week/days/day/slots/subjects/subject',
        '/SMSDirectoryData/timetables/timetable/week/days/day/slots/subjects/subject/code',
        '/SMSDirectoryData/timetables/timetable/week/days/day/slots/subjects/subject/teachers',
        '/SMSDirectoryData/timetables/timetable/week/days/day/slots/subjects/subject/rooms',
    ];
}

