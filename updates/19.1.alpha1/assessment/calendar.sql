-- Assessment Calendar Feature Flag
INSERT INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES (
   'assessment_calendar_enabled',
   1,
   'Assessment Calendar',
   'Is the assessment calendar enabled?',
   3,
   null,
   11,
   'true',
   'false',
   'false',
   'settings'
);