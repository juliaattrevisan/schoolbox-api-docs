INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`)  VALUES (
        'saml_enabled',
        (IF ((SELECT `value` FROM `config` `c` WHERE `c`.`name` = 'saml_sso_url' AND `c`.`value` != ''), '0', '1')),
        'Enable Single Sign-On',
        'Check this to enable Single Sign On via SAML',
        '3',
        (SELECT `id` FROM lu_config_type WHERE `type` like 'Security'),
        'true'
);
