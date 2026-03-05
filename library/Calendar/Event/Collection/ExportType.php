<?php

namespace Calendar\Event\Collection;

/**
 * This class defines export types for the Calendar\Event\Collection namespace.
 * They were originally hard-coded in www/calendar/export.php.
 */
class ExportType
{
    // This is the 'All' option in the export modal on the main calendar page.
    public const string ALL = 'all';

    // This is the 'All events (including external events)' option in the export modal on the main calendar page.
    public const string ALL_EXTERNAL = 'all_external';

    // This is the 'Events for campus' option in the export modal on the main calendar page.
    public const string CAMPUS = 'campus';

    // This is the 'Due Work' option in the export modal on the main calendar page.
    public const string DUE_WORK = 'duework';

    // This is the 'EOTC' option in the export modal on the main calendar page.
    public const string EOTC_ALL = 'eotc_all';

    // This is the 'Events by type' option in the export modal on the main calendar page.
    public const string EVENT_TYPE = 'event_type';

    // This is the 'External events' option in the export modal on the main calendar page.
    public const string EXTERNAL = 'external';

    // This is the 'Events by group' option in the export modal on the main calendar page.
    public const string GROUP = 'group';

    // This is the 'Personal events only' option in the export modal on the main calendar page.
    public const string PERSONAL = 'personal';

    // An export of the calendar for a particular resource.
    // Example URL: https://docker.dev.schoolbox.cloud/resource-booking/item/8
    public const string RESOURCE = 'resource';

    // This is the 'Resource Booking' option in the export modal on the main calendar page.
    public const string RESOURCES = 'resources';

    // An export of the calendar for a particular category of resources.
    // Example URL: https://docker.dev.schoolbox.cloud/resource-booking/category/1
    public const string RESOURCE_CATEGORY = 'resource_category';

    // This is the 'Timetable classes' option in the export modal on the main calendar page.
    public const string TIMETABLE = 'timetable';

    // This is the 'Timetable days' option in the export modal on the main calendar page.
    public const string TIMETABLE_DAYS = 'timetabledays';
}
