-- Add new config
INSERT INTO config (`name`, `title`, `value`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'reporting_activity_enable',
    'Enable Reporting Activity',
    true,
    "Toggling this to ‘ON’ will allow privileged users to create reporting activities in the LMS.",
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    (SELECT id FROM lu_config_type WHERE type = 'LMS Policy'),
    'true',
    'false',
    'false',
    'settings',
    (SELECT MAX(sequence)+1 FROM `config` c)
);