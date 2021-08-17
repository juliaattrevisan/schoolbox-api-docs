INSERT INTO schoolbox.config (name, value, title, description, field_id, options, type_id, editable, hidden, required,
                              module, sequence)
VALUES ('lms_enabled', '1', 'Enable LMS', 'If ON, the LMS features will be configurable', 3, null, (SELECT id FROM `lu_config_type` WHERE `type` = 'Modules'), null, 'true',
        null, 'settings', null);

