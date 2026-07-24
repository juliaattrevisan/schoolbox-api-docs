SET @socs_config_id = 29;

INSERT IGNORE INTO config (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
    'socs_import_co_curricular',
    '0',
    'Import Co-Curricular',
    'If ON, SOCS co-curricular clubs, events, and registrations will be imported.',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    @socs_config_id,
    'true',
    'false',
    'false',
    'settings',
    13
);
