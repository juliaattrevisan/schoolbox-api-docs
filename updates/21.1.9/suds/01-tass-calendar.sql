UPDATE `event` e
INNER JOIN event_source es ON e.id = es.event_id
SET e.deleted_at = NOW()
WHERE source_name IN (
	'ParentCalendar',
	'TeacherCalendar',
	'StudentCalendar',
	'ParentNotices',
	'TeacherNotices',
	'StudentNotices'
);

UPDATE event_source
SET deleted_at = NOW()
WHERE source_name IN (
	'ParentCalendar',
	'TeacherCalendar',
	'StudentCalendar',
	'ParentNotices',
	'TeacherNotices',
	'StudentNotices'
);