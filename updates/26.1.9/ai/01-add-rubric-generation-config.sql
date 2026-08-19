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
        `sequence`
    )
VALUES (
    'ai_rubric_generation',
    'AI Rubric Generation',
    'Configure AI settings for rubric generation.',
    (
        SELECT id
        FROM lu_config_field
        WHERE field = 'json'
    ),
    (
        SELECT id
        FROM lu_config_type
        WHERE type = 'AI'
    ),
    'true',
    'false',
    'false',
    'ai-services',
    (
        SELECT MAX(sequence) + 1
        FROM config c
        WHERE c.module = 'ai-services'
    )
);
