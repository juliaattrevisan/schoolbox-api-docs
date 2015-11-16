INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_login_background',
        'Login Background Colour',
        'rgba(0, 0, 0, 0.4)',
        'Sets the background colour of the login form.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false')
ON DUPLICATE KEY UPDATE configID = configID;
