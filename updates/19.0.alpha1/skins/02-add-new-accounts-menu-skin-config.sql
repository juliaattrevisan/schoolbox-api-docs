-- Config for account menu text colour. The default value will be based from the value of `skin_colour_offcanvas_foreground`.
INSERT INTO `config`
    (
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
        `module`
    )
    VALUES
    (
        'accounts_menu_text_colour',
        (SELECT `conf`.`value` FROM `config` `conf` WHERE conf.`name` = 'skin_colour_offcanvas_foreground' LIMIT 1),
        'Accounts Menu Text Colour',
        'Sets the icon/label colour in the account menu.',
        (SELECT id FROM lu_config_field WHERE field = 'colour'),
        NULL,
        (SELECT id FROM lu_config_type WHERE `type` = 'Skin Config'),
        'true',
        'false',
        'false',
        'settings'
    );

-- Config for account menu hover colour. The default value will be based from the value of `skin_colour_offcanvas_hover`.
INSERT INTO `config`
    (
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
        `module`
    )
    VALUES
    (
        'accounts_menu_hover_colour',
        (SELECT `conf`.`value` FROM `config` `conf` WHERE conf.`name` = 'skin_colour_offcanvas_hover' LIMIT 1),
        'Accounts Menu Hover Colour',
        'Sets the hover colour in the accounts menu.',
        (SELECT id FROM lu_config_field WHERE field = 'colour'),
        NULL,
        (SELECT id FROM lu_config_type WHERE `type` = 'Skin Config'),
        'true',
        'false',
        'false',
        'settings'
    );
