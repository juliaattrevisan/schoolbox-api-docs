INSERT INTO `lu_config_type` (`type`) VALUES ('Skin Config');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES (
    'skin_builder',
    'Use Skin Builder?',
    -- On for demo, off for all others
    (IF ((SELECT COUNT(*) FROM `config` `c` WHERE `c`.`name` = 'site_location' AND `c`.`value` = 'demo'), '1', '0')),
    'Do you wish to use the skin builder to style your Schoolbox? If this is switched on, your custom skin and any custom templates will be ignored.',
    5, -- checkbox
    (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
    'true',
    'false'
);

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_navigation_foreground',
        'Navigation Foreground Colour',
        'rgb(255, 255, 255)',
        'Used for main navigation icon colours and label colours.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_navigation_background',
        'Navigation Background Colour',
        'rgb(76, 0, 176)',
        'Used for main navigation background. This inverts with Primary Foreground colour in the Search panel.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_navigation_hover',
        'Navigation Hover/Accent Colour',
        'rgb(133, 82, 201)',
        'Used for main navigation hover and accent (border) colours in the Search field.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_offcanvas_foreground',
        'Off-Canvas Foreground Colour',
        'rgb(0, 206, 10)',
        'Used for off-canvas navigation icon colours and label colours.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_offcanvas_background',
        'Off-Canvas Background Colour',
        'rgb(2, 137, 0)',
        'Used for off-canvas navigation background.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_offcanvas_hover',
        'Off-Canvas Hover/Accent Colour',
        'rgb(158, 255, 171)',
        'Used for off-canvas navigation hover.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_off_canvas',
        'Off-Canvas Colour',
        'rgb(1, 52, 0)',
        'Set the colour behind the left and right columns',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_content_ui_foreground',
        'Content Interface Foreground Colour',
        'rgb(255, 57, 168)',
        'Used for foreground colours in interface elements in the main content area.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_content_ui_background',
        'Content Interface Background Colour',
        'rgb(255, 204, 232)',
        'Used for background colours in interface elements in the main content area.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_content_ui_submit_foreground',
        'Submit Button Foreground Colour',
        'rgb(255, 0, 0)',
        'Foreground text colour used in Submit buttons commonly found in Components and Form actions.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_content_ui_submit_background',
        'Submit Background Colour',
        'rgb(0, 0, 0)',
        'Background colour used in Submit buttons commonly found in Components and Form actions.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_content_ui_hover',
        'Content Interface Hover/Accent Colour',
        'rgb(255, 229, 244)',
        'Used for hover colours in some interface elements in the main content area.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_content_ui_selected',
        'Content Interface Selected/Active Colour',
        'rgb(255, 255, 255)',
        'Used to indicate a currently active, or selected, state. Applies to elements in the main content area.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_accent_foreground',
        'Accent Foreground Colour',
        'rgb(255, 172, 0)',
        'Foreground colour for dropzones. This also controls heading colours in the left off-canvas menu.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_accent_background',
        'Accent Background Colour',
        'rgb(255, 233, 189)',
        'Background colour for dropzones. This is also the colour of text you select with your mouse/keyboard in browser.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_accent_hover',
        'Accent Hover Colour',
        'rgb(255, 244, 222)',
        'Miscellaneous hover effect for dropzones. This also controls shadow colour on flyout menus.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_logo_background',
        'Crest Background',
        'rgb(255, 255, 255)',
        'The background for your school crest.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_body_bg_colour',
        'Body Background Colour',
        'rgb(237, 237, 237)',
        'Used for background colours and outlines',
        '11',(SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
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

--INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
--VALUES ('skin_primary_colour',
--        'Primary Colour',
--        'rgb(0, 102, 176)',
--        'Used for headers buttons, navigation and links',
--        '11',
--        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
--        'true',
--        'false');
--
--INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
--VALUES ('skin_secondary_colour',
--        'Secondary Colour',
--        'rgb(189, 215, 234)',
--        'Used for actions and form elements',
--        '11',
--        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
--        'true',
--        'false');
--
--INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
--VALUES ('skin_tertiary_colour',
--        'Tertiary Colour',
--        'rgb(0, 102, 176)',
--        'Used for background colours and outlines',
--        '11',
--        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
--        'true',
--        'false');