-- Track when persisted Skin Config values last changed to append to skin.css.php links

INSERT INTO
    `config` (
        `name`,
        `value`,
        `title`,
        `description`,
        `options`,
        `field_id`,
        `type_id`,
        `editable`,
        `hidden`,
        `required`,
        `module`,
        `sequence`
    )
VALUES (
        'skin_config_last_modified',
        UNIX_TIMESTAMP(),
        'Skin Config Last Modified At',
        'The last time Skin Config was modified',
        '',
        (
            SELECT id
            FROM lu_config_field
            WHERE
                `field` = 'input'
        ),
        (
            SELECT id
            FROM lu_config_type
            WHERE
                `type` = 'Skin Config'
        ),
        'true',
        'true',
        'false',
        'settings',
        NULL
    );
