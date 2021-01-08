INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
           'comms_statistics_enabled',
           'true',
           'Enable News Statistics',
           'If ON, the news statistics will be available to view',
           (SELECT id FROM `lu_config_field` WHERE `field` = 'boolean'),
           null,
           null,
           'true',
           'true',
           'false',
           'comms',
           (SELECT MAX(sequence) + 1 FROM `config` c WHERE `module` = 'comms')
       );

INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
           'eportfolio_enabled',
           'true',
           'Enable ePortfolio',
           'If ON, eportfolio feature will be available for users to enable',
           (SELECT id FROM `lu_config_field` WHERE `field` = 'boolean'),
           null,
           (SELECT id FROM `lu_config_type` WHERE `type` = 'Modules'),
           'true',
           'true',
           'false',
           'settings',
            (SELECT MAX(sequence)+1 FROM config c WHERE type_id = (SELECT id FROM lu_config_type WHERE `type` = 'Modules'))
       );


INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
           'staff_directory_enabled',
           'true',
           'Enable Staff Directory',
           'If ON, staff directory feature will be available for users to visit',
           (SELECT id FROM `lu_config_field` WHERE `field` = 'boolean'),
           null,
           (SELECT id FROM `lu_config_type` WHERE `type` = 'External DB'),
           'true',
           'false',
           'false',
           'settings',
           (SELECT MAX(sequence)+1 FROM config c WHERE type_id = (SELECT id FROM lu_config_type WHERE `type` = 'External DB'))
       );


INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
           'parent_directory_enabled',
           'true',
           'Enable Parent Directory',
           'If ON, parent directory feature will be available for users to visit',
           (SELECT id FROM `lu_config_field` WHERE `field` = 'boolean'),
           null,
           (SELECT id FROM `lu_config_type` WHERE `type` = 'External DB'),
           'true',
           'false',
           'false',
           'settings',
           (SELECT MAX(sequence)+1 FROM config c WHERE type_id = (SELECT id FROM lu_config_type WHERE `type` = 'External DB'))
       );
