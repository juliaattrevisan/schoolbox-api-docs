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
    public const string RESOURCE = 'resource';
    public const string RESOURCES = 'resources';
    public const string RESOURCE_CATEGORY = 'resource_category';
    public const string TIMETABLE = 'timetable';
    public const string TIMETABLE_DAYS = 'timetabledays';
}
