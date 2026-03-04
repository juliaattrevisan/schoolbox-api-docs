<?php

namespace Calendar\Event\Collection;

/**
 * This class defines export types for the Calendar\Event\Collection namespace.
 * They were originally hard-coded in www/calendar/export.php.
 */
class ExportType
{
    public const string ALL = 'all';
    public const string ALL_EXTERNAL = 'all_external';
    public const string CAMPUS = 'campus';
    public const string DUE_WORK = 'duework';
    public const string EOTC_ALL = 'eotc_all';
    public const string EVENT_TYPE = 'event_type';
    public const string EXTERNAL = 'external';
    public const string GROUP = 'group';
    public const string PERSONAL = 'personal';

    // An export of the calendar for a particular resource.
    // Example URL: https://docker.dev.schoolbox.cloud/resource-booking/item/8
    public const string RESOURCE = 'resource';

    // I don't think this is used anymore, but a case for it still exists in www/calendar/export.php
    public const string RESOURCES = 'resources';

    // An export of the calendar for a particular category of resources.
    // Example URL: https://docker.dev.schoolbox.cloud/resource-booking/category/1
    public const string RESOURCE_CATEGORY = 'resource_category';
    public const string TIMETABLE = 'timetable';
    public const string TIMETABLE_DAYS = 'timetabledays';
}
