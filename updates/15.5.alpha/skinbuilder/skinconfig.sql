INSERT INTO `lu_config_type` (`type`) VALUES ('Skin Config');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_primary_colour',
        'Primary Colour',
        '#0066b0',
        'Used for headers buttons, navigation and links',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_secondary_colour',
        'Secondary Colour',
        '#bdd7ea',
        'Used for actions and form elements',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_tertiary_colour',
        'Tertiary Colour',
        '#0066b0',
        'Used for background colours and outlines',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_body_bg_colour',
        'Body Background Colour',
        '#ededed',
        'Used for background colours and outlines',
        '11',(SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_tab_bg_colour',
        'Tab Background Colour',
        '#0066b0',
        'Set the colour behind the top tab bar behind icons',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_off_canvas_bg_colour',
        'Off Canvas Colour',
        '#ffffff',
        'Set the colour behind the left and right columns',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_logo_square',
        'Logo Square',
        '',
        'Upload a square image at least 180x180 pixels, this will be used for favicons, tiles and in mobile',
        '12',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_logo_large',
        'Logo Large',
        '',
        'Upload image at least 196px wide. Will be used in left hand navigation',
        '12',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_logo_login',
        'Logo Login',
        '',
        'Upload image to be used above login form',
        '12',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_login_bg',
        'Login Background',
        '',
        'Upload large image to be used behind login form',
        '12',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');