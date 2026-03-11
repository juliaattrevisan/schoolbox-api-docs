<?php

namespace Calendar\Event\Collection;

/**
 * This class defines export types for the Calendar\Event\Collection namespace.
 * They were originally hard-coded in www/calendar/export.php.
 */
class ExportType
{
    // Note some of the _FILE constants below are unused, and some are inconsistently named.
    // That is because at the time this file was created, the export files generated in calendar/export.php were inconsistently named.
    // The addition of this file was not meant to change behaviour, so the export filenames have been left in their inconsistent state.

    // This is the 'All' option in the export modal on the main calendar page.
    public const string ALL = 'all';
    public const string ALL_FILE = self::ALL;

    // This is the 'All events (including external events)' option in the export modal on the main calendar page.
    public const string ALL_EXTERNAL = 'all_external';
    public const string ALL_EXTERNAL_FILE = self::ALL_EXTERNAL;

    // This is the 'Events for campus' option in the export modal on the main calendar page.
    public const string CAMPUS = 'campus';
    public const string CAMPUS_FILE = self::CAMPUS . '%d';

    // This is the 'Due Work' option in the export modal on the main calendar page.
    public const string DUE_WORK = 'duework';
    public const string DUE_WORK_FILE = self::DUE_WORK;

    // This is the 'EOTC' option in the export modal on the main calendar page.
    public const string EOTC_ALL = 'eotc_all';
    public const string EOTC_ALL_FILE = self::EOTC_ALL;

    // This is the 'Events by type' option in the export modal on the main calendar page.
    public const string EVENT_TYPE = 'event_type';
    public const string EVENT_TYPE_FILE = 'eventType%d';

    // This is the 'External events' option in the export modal on the main calendar page.
    public const string EXTERNAL = 'external';
    public const string EXTERNAL_FILE = self::EXTERNAL;

    // This is the 'Events by group' option in the export modal on the main calendar page.
    public const string GROUP = 'group';
    public const string GROUP_FILE = self::GROUP . '%d';

    // This is the 'Personal events only' option in the export modal on the main calendar page.
    public const string PERSONAL = 'personal';
    public const string PERSONAL_FILE = self::PERSONAL;

    // An export of the calendar for a particular resource.
    // Example URL: https://docker.dev.schoolbox.cloud/resource-booking/item/8
    public const string RESOURCE = 'resource';
    public const string RESOURCE_FILE = self::RESOURCE . '%';

    // This is the 'Resource Booking' option in the export modal on the main calendar page.
    public const string RESOURCES = 'resources';
    public const string RESOURCES_FILE = self::RESOURCES;

    // An export of the calendar for a particular category of resources.
    // Example URL: https://docker.dev.schoolbox.cloud/resource-booking/category/1
    public const string RESOURCE_CATEGORY = 'resource_category';
    public const string RESOURCE_CATEGORY_FILE = 'resourceCategory%d';

    // This is the 'Timetable classes' option in the export modal on the main calendar page.
    public const string TIMETABLE = 'timetable';
    public const string TIMETABLE_FILE = self::TIMETABLE;

    // This is the 'Timetable days' option in the export modal on the main calendar page.
    public const string TIMETABLE_DAYS = 'timetabledays';
    public const string TIMETABLE_DAYS_FILE = self::TIMETABLE_DAYS;
}
