-- Button/input scale as relative to the font size
INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`,`editable`,`required`)
VALUES (
    'skin_button_input_scale',
    'Button/Input Scale',
    250,
    'Sets the size of buttons and text inputs as a percentage of the font size.',
    1, -- input
    (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
    'true',
    'false');
