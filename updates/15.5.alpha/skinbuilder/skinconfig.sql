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
        'Main Navigation Icon Colour',
        '',
        'Sets the icon/label colour in the main (top) navigation.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_navigation_background',
        'Main Navigation Background Colour',
        '',
        'Sets the background colour in the main (top) navigation.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_navigation_hover',
        'Main Navigation Hover Colour',
        '',
        'Sets the hover colour in the main (top) navigation.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');


INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_offcanvas_foreground',
        'Left-Side-Menu Text Colour',
        '',
        'Sets the icon/label colour in the left-side-menu.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_offcanvas_background',
        'Left-Side-Menu Background Colour',
        '',
        'Sets the background colour in the left-side-menu.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_offcanvas_hover',
        'Left-Side-Menu Hover Colour',
        '',
        'Sets the hover colour in the left-side-menu.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_off_canvas',
        'Accounts Menu Background Colour',
        '',
        'Sets the background colour in the accounts menu (inside the left-side-menu).',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');


INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_content_ui_foreground',
        'Content Interface Foreground Colour',
        '',
        'Sets the foreground colour in interface elements in the main content area.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_content_ui_background',
        'Content Interface Background Colour',
        '',
        'Sets the background colour in interface elements in the main content area.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_content_ui_hover',
        'Content Interface Hover Colour',
        '',
        'Sets the hover colours in some interface elements in the main content area.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_content_ui_selected',
        'Content Interface Selected Colour',
        '',
        'Sets the selected-state colour in interface elements in the main content area.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');


INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_content_ui_submit_foreground',
        'Submit Button Text Colour',
        '',
        'Sets the text colour in Submit buttons (commonly found in Components and Form actions).',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_content_ui_submit_background',
        'Submit Button Background Colour',
        '',
        'Sets the background colour in Submit buttons (commonly found in Components and Form actions).',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');


INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_accent_foreground',
        'Accent Foreground Colour',
        '',
        'Sets the foreground colour in dropzones (file attach areas).',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_accent_background',
        'Accent Background Colour',
        '',
        'Sets the background colour for dropzones (file attach areas) and your browser\'s mouse/keyboard text highlight.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_accent_hover',
        'Accent Hover Colour',
        '',
        'Sets the hover colour for dropzones (file attach area) and the shadow colour on fly-out menus.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');


INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_logo_background',
        'Crest Background',
        '',
        'Sets the background colour for your school crest in the left-side-menu.',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');


INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_body_bg_colour',
        'Body Background Colour',
        '',
        'Sets the background colour in the main content area.',
        '11',(SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');


INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_logo_square',
        'Logo Square',
        '',
        'Upload a square image at least 180x180 pixels. This will be used for favicons, tiles and the mobile view.',
        '12',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_logo_large',
        'Logo Large',
        '',
        'Upload an image at least 196 pixels wide. This will be used in the left-side-menu.',
        '12',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_logo_login',
        'Logo Login',
        '',
        'Upload an image to be used above the login form on the login page.',
        '12',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_login_bg',
        'Login Background',
        '',
        'Upload a large image to be used behind the login form on the login page.',
        '12',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

-- INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
-- VALUES ('skin_primary_colour',
--        'Primary Colour',
--        'rgb(0, 102, 176)',
--        'Used for headers buttons, navigation and links',
--        '11',
--        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
--        'true',
--        'false');
--
-- INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
-- VALUES ('skin_secondary_colour',
--        'Secondary Colour',
--        'rgb(189, 215, 234)',
--        'Used for actions and form elements',
--        '11',
--        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
--        'true',
--        'false');
--
-- INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
-- VALUES ('skin_tertiary_colour',
--        'Tertiary Colour',
--        'rgb(0, 102, 176)',
--        'Used for background colours and outlines',
--        '11',
--        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
--        'true',
--        'false');