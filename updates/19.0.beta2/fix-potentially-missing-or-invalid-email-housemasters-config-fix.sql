-- Possible that `email_housemasters` config flag may have be missing.
-- Looking at 19.0.alpha1/add-new-config-for-class-list-housemaster.sql my INSERT query is INSERT INTO `schoolbox`.`config`
-- This migration script is to make sure that this flag is being inserted and has the right values especially in the
-- `field_id` and `type_id` fields.

INSERT INTO `config` (
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `options`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`)
        VALUES (
            'email_housemasters',
            '1',
            'Allow Email Housemasters from Class List',
            'If ticked, allows staff to email housemasters of a class with a single button',
            (SELECT id FROM lu_config_field WHERE field = 'boolean'),
            null,
            (SELECT id FROM lu_config_type WHERE `type` = 'Policy'),
            'true',
            'false',
            'false',
            'settings'
            )
        ON DUPLICATE KEY UPDATE
            `field_id` = (SELECT id FROM lu_config_field WHERE field = 'boolean'),
            `type_id` = (SELECT id FROM lu_config_type WHERE `type` = 'Policy'),
            `options` = null;
