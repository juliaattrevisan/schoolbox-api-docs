INSERT INTO
    config (
        `name`,
        `title`,
        `description`,
        `field_id`,
        `type_id`,
        `options`,
        `editable`,
        `hidden`,
        `required`,
        `module`,
        `sequence`,
        `value`
    )
VALUES (
        'openai_image_output_quality',
        'Image Output Quality',
        'Select which output quality you wish to use for image generation. A higher quality will improve the result of more complex images but will take longer to generate and use more credit',
        (
            SELECT id
            FROM lu_config_field
            WHERE
                field = 'select'
        ),
        (
            SELECT id
            FROM lu_config_type
            WHERE
                type = 'AI'
        ),
        'high:medium:low:auto',
        'true',
        'false',
        'false',
        'openai-image',
        10,
        'low'
    );

UPDATE config
SET
    `sequence` = 11
WHERE
    `name` = 'openai_image_system_prompt'
LIMIT 1;
