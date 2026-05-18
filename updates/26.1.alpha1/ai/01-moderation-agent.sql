-- re sequence ai-services
update config set sequence = 2 where name = 'ai_text_chat';
update config set sequence = 3 where name = 'ai_lessonplan';
update config set sequence = 4 where name = 'ai_dialogagent';
update config set sequence = 5 where name = 'ai_differentiation';
update config set sequence = 6 where name = 'ai_image_generation';
update config set sequence = 7 where name = 'ai_learning_activity_customisation';
update config set sequence = 8 where name = 'ai_moderation_key';

-- Add new AI Moderation Agent configuration
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
           'ai_moderation_agent',
           'AI Moderation Agent',
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
           'ai-services',
           1
       );

ALTER TABLE openai_log
    MODIFY feature ENUM (
        'chat', 
        'lessonplan', 
        'image', 
        'differentiation', 
        'dialogagent', 
        'learningactivitycustomisation',
        'aimoderationagent'
    ) NULL;

