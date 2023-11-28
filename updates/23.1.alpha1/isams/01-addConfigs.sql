SELECT `id` INTO @type FROM `lu_config_type` WHERE `type` = 'External DB';
SELECT max(sequence) INTO @sequence FROM `config` c WHERE type_id = @type;
INSERT INTO `config`(
    `name`,
    `title`,
    `value`,
    `description`,
    `field_id`,
    `type_id`,
    `options`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    `sequence`
) VALUES (
    'external_isams_rest_api_host',
    'iSAMS REST API Host',
    '',
    'The host name for your iSAMS REST API',
    (SELECT `id` FROM `lu_config_field` WHERE `field` = 'uri'),
    @type,
    '',
    'true',
    'false',
    'false',
    'settings',
    @sequence + 1
), (
    'external_isams_rest_api_client_id',
    'iSAMS REST API Client ID',
    '',
    'The client ID for your iSAMS REST API',
    (SELECT `id` FROM `lu_config_field` WHERE `field` = 'input'),
    @type,
    '',
    'true',
    'false',
    'false',
    'settings',
    @sequence + 2
), (
    'external_isams_rest_api_client_secret',
    'iSAMS REST API Client Secret',
    '',
    'The client secret for your iSAMS REST API',
    (SELECT `id` FROM `lu_config_field` WHERE `field` = 'password'),
    @type,
    '',
    'true',
    'false',
    'false',
    'settings',
    @sequence + 3
),(
     'external_isams_student_registers',
     'iSAMS Student Registers Filter',
     '',
     'a csv list of registers,and field codes to show on the Student Registers page',
     (SELECT `id` FROM `lu_config_field` WHERE `field` = 'textarea'),
     @type,
     '',
     'true',
     'false',
     'false',
     'settings',
     @sequence + 3
 ),(
     'external_isams_financial_registers',
     'iSAMS Financial Registers Filter',
     '',
     'a csv list of registers,and field codes to show on the Financial Registers page',
     (SELECT `id` FROM `lu_config_field` WHERE `field` = 'textarea'),
     @type,
     '',
     'true',
     'false',
     'false',
     'settings',
     @sequence + 3
 )

