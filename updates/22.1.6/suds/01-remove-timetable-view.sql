-- Remove the much-maligned timetable_view option

DELETE FROM `config`
WHERE `name` = 'timetable_view'
LIMIT 1;
