INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required,
                              module, sequence)
VALUES ('lms_enabled', '1', 'Enable LMS', 'If ON, the LMS features will be configurable', (SELECT id FROM `lu_config_field` WHERE `field` = 'boolean'),
        null, (SELECT id FROM `lu_config_type` WHERE `type` = 'Modules'), 'false', 'true', 'false', 'settings', null);

