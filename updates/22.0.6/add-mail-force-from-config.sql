INSERT INTO `config` (`name`,
                      `value`,
                      `title`,
                      `description`,
                      `field_id`,
                      `type_id`,
                      `editable`,
                      `hidden`,
                      `required`,
                      `module`,
                      `sequence`)
VALUES ('mail_force_from_system',
        0,
        'Force mail to send FROM system',
        'If toggled to ''ON'', all mail sent from Schoolbox will have the "Email From Address" as its FROM address: this may be required for some mail servers in order to send emails to recipients. (The REPLY-TO header will be set to the sender''s address.)',
        (SELECT id FROM `lu_config_field` WHERE field = 'boolean'),
        (SELECT id FROM `lu_config_type` WHERE type = 'Email'),
        'true',
        'false',
        'true',
        'settings',
        (SELECT max(`sequence`) + 1
         FROM `config` cf
         WHERE `module` = 'settings'
           AND `type_id` = (SELECT id FROM `lu_config_type` WHERE type = 'Email')));
