
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
    'sk-', -- TODO: confirm which key to use
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
