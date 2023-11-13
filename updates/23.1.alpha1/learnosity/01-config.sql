-- Add new config
INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'learnosity_consumer_key',
    'Learnosity Consumer Key',
    'Learnosity Consumer Key',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'true',
    'true',
    'false',
    'settings',
    (SELECT MAX(sequence)+1 FROM `config` c)
);

-- Add new config
INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'learnosity_consumer_secret',
    'Learnosity Consumer Secret',
    'Learnosity Consumer Secret',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'true',
    'true',
    'false',
    'settings',
    (SELECT MAX(sequence)+1 FROM `config` c)
);

INSERT INTO
    config (
    name,
    value,
    title,
    description,
    field_id,
    type_id,
    editable,
    hidden,
    required,
    module
)
VALUES (
           'learnosity_version',
           '2023.3.LTS',
           'Learnosity Version Number',
           'Version Number of Learnosity to use', (
               SELECT id
               FROM
                   lu_config_field
               WHERE
                       `field` = "input"
           ), (
               SELECT id
               FROM
                   lu_config_type
               WHERE
                       `type` = "3rd Party Services"
           ),
           'false',
           'true',
           'false',
           'settings'
       );
