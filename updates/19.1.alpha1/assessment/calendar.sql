-- Assessment Calendar Feature Flag
INSERT INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES (
   'assessment_calendar_enabled',
   1,
   'Assessment Calendar',
   'A staff only calendar that provides the ability to see all assessments due dates and filter them to specific
    sets of users. Helps provide oversight on student work load and assessment collisions.',
   (SELECT id from lu_config_field WHERE name = 'boolean'),
   null,
   (SELECT id from lu_config_type WHERE name = 'Modules'),
   'true',
   'false',
   'false',
   'settings'
);