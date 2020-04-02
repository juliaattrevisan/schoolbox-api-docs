INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`)
VALUES (
           'saml_entityid',
           (SELECT value FROM `config` as c WHERE name like 'saml_sso_url'),
           'Entity ID',
           'The unique identifier for the Identity Provider',
           (SELECT id FROM `lu_config_field` WHERE `field` = 'input'),
           null,
           (SELECT id FROM `lu_config_type` WHERE `type` = 'SAML'),
           'true',
           'false',
           'false',
           'settings'
       );


