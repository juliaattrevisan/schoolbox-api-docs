-- Add system prompt configuration for text generation
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
           'openai_text_settings',
           'Text settings (new)',
           '',
           (
               SELECT id
               FROM lu_config_field
               WHERE
                   field = 'input'
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
           'openai-text',
           (
               SELECT MAX(sequence) + 1
               FROM config c
               WHERE
                   c.module = "openai-text"
           )
       );

-- Push down the 'openai-image' config following the text generation config
UPDATE config
SET
    sequence = sequence + 1
WHERE
    module = 'openai-image';
