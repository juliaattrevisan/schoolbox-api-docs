-- Insert new config field 'date'
INSERT INTO lu_config_field (id, field) VALUES (18, 'date');

-- Add new config
INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'date_increment_epoch',
    'Dataset is up to date indicator',
    'Date at which dataset was up to date. NOTE: This is only meant to be used in trial instances using demo dataset.',
    (SELECT id FROM lu_config_field WHERE field = 'date'),
    (SELECT id FROM lu_config_type WHERE type = 'Resources'),
    'false',
    'true',
    'false',
    'settings',
    (SELECT * FROM (SELECT MAX(sequence)+1 FROM config WHERE type_id = ((SELECT id FROM lu_config_type WHERE type = 'Resources'))) t1)
);
