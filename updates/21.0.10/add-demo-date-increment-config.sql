INSERT INTO config (`name`, `title`, `description`, `editable`, `hidden`, `required`, `module`, `field_id`, `type_id`)
VALUES (
        'date_increment_epoch',
        'Dataset is up to date indicator',
        'The date that indicates whether the dataset is still up to date. NOTE: This is only meant to be used in trial instances using demo dataset.',
        'false',
        'true',
        'false',
        'settings',
        (SELECT id FROM lu_config_field WHERE field = 'input'),
        NULL
);
