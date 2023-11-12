INSERT INTO `config`(`name`,
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
VALUES ('openai_key',
        '',
        'OpenAI Key',
        'This key allows GPT based AI assistant services to be provided when creating content.  Key can be create at https://openai.com/',
        (SELECT `id` FROM `lu_config_field` WHERE `field` = 'input'),
        (SELECT `id` FROM `lu_config_type` WHERE `type` = '3rd Party Services'),
        'true',
        'false',
        'false',
        'settings',
        (SELECT max(sequence) + 1
         FROM `config` c
         WHERE type_id = (SELECT `id` FROM `lu_config_type` WHERE `type` = '3rd Party Services')));
