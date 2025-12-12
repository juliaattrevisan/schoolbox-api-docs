-- Add a feature toggle for Advanced User Interface in Skin Config
SET
    @skin_config_id = (
        SELECT id
        FROM lu_config_type
        WHERE
            type = 'Skin Config'
    );

INSERT INTO
    config (
        `name`,
        `title`,
        `value`,
        `description`,
        `field_id`,
        `type_id`,
        `editable`,
        `hidden`,
        `required`,
        `module`,
        `sequence`
    )
VALUES (
        'advanced_ui_enabled',
        'Enable Advanced User Interface',
        false,
        'Enable Advanced User Interface Configuration.',
        (
            SELECT id
            FROM lu_config_field
            WHERE
                field = 'boolean'
        ),
        @skin_config_id,
        'true',
        'true',
        'false',
        'settings',
        (
            SELECT MAX(sequence) + 1
            FROM `config` c
            WHERE
                c.module = 'settings'
                AND c.type_id = @skin_config_id
        )
    );
