-- Insert new config field 'date'
INSERT INTO lu_config_field (field) VALUES ('date');

-- Add new config
INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`)
VALUES (
    'date_increment_epoch',
    'Dataset is up to date indicator',
    'Date at which dataset was up to date. NOTE: This is only meant to be used in trial instances using demo dataset.',
    (SELECT id FROM lu_config_field WHERE field = 'date'),
    (SELECT id FROM lu_config_type WHERE type = 'Resources'),
    'false',
    'true',
    'false',
    'settings'
);
