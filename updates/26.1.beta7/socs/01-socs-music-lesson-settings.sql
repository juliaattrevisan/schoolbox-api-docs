SET @socs_config_id = 29;

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

UPDATE config
SET type_id = @socs_config_id,
    sequence = CASE name
        WHEN 'socs_import_music_lessons' THEN 9
        WHEN 'socs_music_lessons_name' THEN 10
        WHEN 'socs_music_lessons_default_event_type' THEN 11
        WHEN 'socs_import_sports_fixtures' THEN 12
    END
WHERE name IN (
    'socs_import_music_lessons',
    'socs_music_lessons_name',
    'socs_music_lessons_default_event_type',
    'socs_import_sports_fixtures'
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
