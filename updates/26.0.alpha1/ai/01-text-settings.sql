INSERT INTO lu_config_field (id, field)
VALUES (21, 'json');

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
           'ai_text_chat',
           'Text Chat',
           '',
           (
               SELECT id
               FROM lu_config_field
               WHERE
                   field = 'json'
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
       ),
       (
           'ai_lessonplan',
           'Lesson Plan',
           '',
           (
               SELECT id
               FROM lu_config_field
               WHERE
                   field = 'json'
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
               SELECT MAX(sequence) + 2
               FROM config c
               WHERE
                   c.module = "openai-text"
           )
       ),
       (
           'ai_dialogagent',
           'Discussion Learning Activity',
           '',
           (
               SELECT id
               FROM lu_config_field
               WHERE
                   field = 'json'
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
               SELECT MAX(sequence) + 3
               FROM config c
               WHERE
                   c.module = "openai-text"
           )
       ),
       (
           'ai_differentiation',
           'Differentiation Profiles',
           '',
           (
               SELECT id
               FROM lu_config_field
               WHERE
                   field = 'json'
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
               SELECT MAX(sequence) + 4
               FROM config c
               WHERE
                   c.module = "openai-text"
           )
       );

-- Push down the 'openai-image' config following the text generation config
UPDATE config
SET
    sequence = sequence + 4
WHERE
    module = 'openai-image';

### deprecate the old fields append (Deprecated) to the title for the following fields
UPDATE config
SET
    title = CONCAT(title, ' (Deprecated)'),
    editable = 'false'
WHERE
    name IN (
        'ai_text_key',
        'ai_text_azure_resource_name',
        'ai_text_azure_deployment_id',
        'ai_text_model'
    );
