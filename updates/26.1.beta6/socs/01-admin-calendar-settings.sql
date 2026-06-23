SET @socs_config_id = 29;

INSERT IGNORE INTO lu_config_type (id, type, sequence)
VALUES (@socs_config_id, 'SOCS', 29);

UPDATE config
SET type_id = @socs_config_id,
    title = CASE name
        WHEN 'socs_endpoint_host' THEN 'General Feed Host'
        ELSE title
    END,
    description = CASE name
        WHEN 'socs_endpoint_host' THEN 'The general SOCS host used for non-fixture feeds.'
        ELSE description
    END,
    sequence = CASE name
        WHEN 'socs_enabled' THEN 1
        WHEN 'socs_endpoint_host' THEN 2
        WHEN 'socs_endpoint_host_fixture' THEN 3
        WHEN 'socs_endpoint_id' THEN 4
        WHEN 'socs_endpoint_token' THEN 5
        ELSE sequence
    END
WHERE name IN (
    'socs_enabled',
    'socs_endpoint_host',
    'socs_endpoint_host_fixture',
    'socs_endpoint_token',
    'socs_endpoint_id'
);

INSERT IGNORE INTO config (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
    'socs_import_calendar_events',
    '0',
    'Import Calendar Events',
    'If ON, SOCS calendar events will be imported from the SOCS calendar feed.',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    @socs_config_id,
    'true',
    'false',
    'false',
    'settings',
    6
);

INSERT IGNORE INTO config (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
    'socs_calendar_name',
    'SOCS Events',
    'Calendar Event Feed Name',
    'The name shown for imported SOCS calendar events in calendar source filters.',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    @socs_config_id,
    'true',
    'false',
    'false',
    'settings',
    7
);

INSERT IGNORE INTO config (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `options`, `sequence`)
VALUES (
    'socs_calendar_default_event_type',
    '1',
    'Default Calendar Category',
    'The calendar category assigned to imported SOCS calendar events.',
    (SELECT id FROM lu_config_field WHERE field = 'selectLookup'),
    @socs_config_id,
    'true',
    'false',
    'false',
    'settings',
    'a:3:{s:6:"mapper";s:34:"Schoolbox\\Calendar\\EventTypeMapper";s:12:"getKeyMethod";s:5:"getId";s:14:"getValueMethod";s:7:"getType";}',
    8
);

INSERT IGNORE INTO config (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
    'socs_import_music_lessons',
    '0',
    'Import Music Lessons',
    'If ON, SOCS music lessons will be imported from the SOCS music lessons feed.',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    @socs_config_id,
    'true',
    'false',
    'false',
    'settings',
    9
);

INSERT IGNORE INTO config (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
    'socs_music_lessons_name',
    'SOCS Music Lessons',
    'Music Lessons Feed Name',
    'The name shown for imported SOCS music lessons in calendar source filters.',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    @socs_config_id,
    'true',
    'false',
    'false',
    'settings',
    10
);

INSERT IGNORE INTO config (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `options`, `sequence`)
VALUES (
    'socs_music_lessons_default_event_type',
    '1',
    'Default Music Lesson Category',
    'The calendar category assigned to imported SOCS music lessons.',
    (SELECT id FROM lu_config_field WHERE field = 'selectLookup'),
    @socs_config_id,
    'true',
    'false',
    'false',
    'settings',
    'a:3:{s:6:"mapper";s:34:"Schoolbox\\Calendar\\EventTypeMapper";s:12:"getKeyMethod";s:5:"getId";s:14:"getValueMethod";s:7:"getType";}',
    11
);

INSERT IGNORE INTO config (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
    'socs_import_sports_fixtures',
    '1',
    'Import Sports Fixtures',
    'If ON, SOCS sports fixtures will be imported from the SOCS fixtures feeds.',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    @socs_config_id,
    'true',
    'false',
    'false',
    'settings',
    12
);

INSERT IGNORE INTO config (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
SELECT 'socs_endpoint_host_fixture',
       COALESCE((SELECT value FROM config WHERE name = 'socs_endpoint_host' LIMIT 1), ''),
       'Sports Fixture Feed Host',
       'The SOCS host used for sports fixtures, teams, and results feeds.',
       (SELECT id FROM lu_config_field WHERE field = 'input'),
       @socs_config_id,
       'true',
       'false',
       'false',
       'settings',
       3;

UPDATE config
SET type_id = @socs_config_id,
    sequence = CASE name
        WHEN 'socs_import_calendar_events' THEN 6
        WHEN 'socs_calendar_name' THEN 7
        WHEN 'socs_calendar_default_event_type' THEN 8
        WHEN 'socs_import_music_lessons' THEN 9
        WHEN 'socs_music_lessons_name' THEN 10
        WHEN 'socs_music_lessons_default_event_type' THEN 11
        WHEN 'socs_import_sports_fixtures' THEN 12
    END
WHERE name IN (
    'socs_import_calendar_events',
    'socs_calendar_name',
    'socs_calendar_default_event_type',
    'socs_import_music_lessons',
    'socs_music_lessons_name',
    'socs_music_lessons_default_event_type',
    'socs_import_sports_fixtures'
);

INSERT INTO calendar_connector (`event_type`, `connector_class`, `config`, `status`)
SELECT 'Event',
       'Socs',
       '{"label":"SOCS Events","luEventType":1}',
       0
WHERE NOT EXISTS (
    SELECT 1
    FROM calendar_connector
    WHERE event_type = 'Event'
      AND connector_class = 'Socs'
);

INSERT INTO calendar_connector (`event_type`, `connector_class`, `config`, `status`)
SELECT 'Event',
       'SocsMusic',
       '{"label":"SOCS Music Lessons","luEventType":1}',
       0
WHERE NOT EXISTS (
    SELECT 1
    FROM calendar_connector
    WHERE event_type = 'Event'
      AND connector_class = 'SocsMusic'
);
