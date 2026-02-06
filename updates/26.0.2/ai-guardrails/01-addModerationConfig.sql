
INSERT INTO config (
    `name`,
    `value`,
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
    'ai_moderation_key',
    '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',
    'AI Moderation Key',
    'Key for the OpenAI Moderation API',
    (SELECT id FROM lu_config_field WHERE `field` = 'json'),
    (SELECT id FROM lu_config_type WHERE `type` = 'AI'),
    'false', -- not school controllable
    'true', -- not school controllable
    'false',
    'ai-services',
    (
        SELECT MAX(sequence) + 1
        FROM config c
        WHERE
            c.module = "ai-services"
    )
);
