INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_form_input_border_color',
        'Form Input Border Color',
        (SELECT c.`value` FROM config c WHERE c.`name` = 'skin_colour_content_ui_background'),
        'Sets the form input and text area border colour',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');

INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES ('skin_colour_form_background_hover_color',
        'Form Background Hover Colour',
        (SELECT c.`value` FROM config c WHERE c.`name` = 'skin_colour_content_ui_hover'),
        'Sets input and text area background hover colour',
        '11',
        (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
        'true',
        'false');