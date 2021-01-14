UPDATE config SET sequence=sequence+1 WHERE type_id =  (SELECT id FROM `lu_config_type` WHERE `type` = 'Site') AND sequence >= 10;

INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
           'site_plan',
           '',
           'Schoolbox Plan',
           'The current plan running on this server, controls what features are available. To see more information about plans visit: https://schoolbox.com.au/pricing. To upgrade or alter your plan please contact your account manager.',
           (SELECT id FROM `lu_config_field` WHERE `field` = 'input'),
           null,
           (SELECT id FROM `lu_config_type` WHERE `type` = 'Site'),
           'false',
           'false',
           'false',
           'settings',
           10
       );



INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
           'comms_statistics_enabled',
           '1',
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
           '1',
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
           '1',
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
           '1',
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

UPDATE config SET type_id =  (SELECT id FROM `lu_config_type` WHERE `type` = 'Modules') WHERE name like 'pastoral_enabled';

UPDATE config SET type_id = (SELECT id FROM `lu_config_type` WHERE `type` = 'Modules'), sequence = 6  WHERE name like 'boarder_leave_enabled';

UPDATE config SET title = 'Enable Notifications'  WHERE name like 'messaging_enabled';
