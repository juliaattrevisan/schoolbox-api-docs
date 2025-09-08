INSERT INTO
    config (
        `name`,
        `title`,
        `description`,
        `field_id`,
        `type_id`,
        `editable`,
        `hidden`,
        `required`,
        `module`,
        sequence
    )
VALUES (
        'text_generation_system_prompt',
        'Text Generation System Prompt',
        'Provide some additional instructions to shape what the AI service generates for all text generation requests.',
        (
            SELECT id
            FROM lu_config_field
            WHERE
                field = 'textarea'
        ),
        (
            SELECT id
            FROM lu_config_type
            WHERE
                type = 'AI'
        ),
        'true',
        'false',
        'false',
        'settings',
        '5'
    );
