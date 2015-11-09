-- Button/input scale as relative to the font size
INSERT INTO `config` (`name`, `title`,`value`, `description`, `field_id`, `type_id`, `options`, `editable`,`required`)
VALUES (
    'skin_button_input_scale',
    'Button/Input Scale',
    250,
    'Sets the size of buttons and text inputs as a proportion of the font size.',
    7, -- select box
    (SELECT `id` FROM lu_config_type WHERE type = 'Skin Config'),
    '150;Tiny (1.5x):200;Small (2x):250;Medium (2.5x):300;Large (3x)',
    'true',
    'false');
