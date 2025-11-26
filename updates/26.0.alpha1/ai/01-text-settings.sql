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
       ),
       (
           'ai_image_generation',
           'Image',
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
               SELECT MAX(sequence) + 5
               FROM config c
               WHERE
                   c.module = "openai-text"
           )
       ),
       (
           'ai_learning_activity_customisation',
           'Learning Activity Customisation',
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
               SELECT MAX(sequence) + 6
               FROM config c
               WHERE
                   c.module = "openai-text"
           )
       );

# -- Push down the 'openai-image' config following the text generation config
# -- Image configuration is the fifth "feature" you can set up
# UPDATE config
# SET
#     sequence = sequence + 5
# WHERE
#     module = 'openai-image';


### deprecate the old fields append (Deprecated) to the title for the following fields
-- Note that these, while "deprecated", are actually going to still be used in the next update script
-- 02-migrate-AI-Creds.php where we actually use their values

UPDATE config
SET
    title = CONCAT(title, ' (Deprecated)'),
    editable = 'false'
WHERE name IN (
    'openai_text_key',
    'openai_text_azure_resource_name',
    'openai_text_azure_deployment_id',
    'openai_text_model',
    'openai_text_endpoint',
    'openai_text_generation_system_prompt',
    'openai_image_endpoint',
    'openai_image_key',
    'openai_image_azure_resource_name',
    'openai_image_azure_deployment_id',
    'openai_image_model',
    'openai_image_system_prompt',
    'openai_image_output_quality'
);
