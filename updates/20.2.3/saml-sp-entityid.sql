UPDATE `config` SET title = 'IDP Entity ID' WHERE name like 'saml_entityid';

INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`,`sequence`)
VALUES (
           'saml_sp_entityid',
           '',
           'Schoolbox Entity ID',
           'The unique identifier for Schoolbox to use as its EntityID. If this is not set, we will use the URL for your instance.',
           (SELECT id FROM `lu_config_field` WHERE `field` = 'input'),
           null,
           (SELECT id FROM `lu_config_type` WHERE `type` = 'SAML'),
           'true',
           'false',
           'false',
           'settings',
           (SELECT MAX(sequence) + 1 FROM config c WHERE `module` = 'settings' AND type_id = (SELECT id FROM lu_config_type WHERE `type` = 'SAML'))
       );
