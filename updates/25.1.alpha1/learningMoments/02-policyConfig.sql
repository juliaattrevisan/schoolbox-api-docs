-- Push down all the other configs in this area
-- Create a hole in config

UPDATE config c
SET c.sequence = c.sequence - 1
WHERE c.module = 'settings'
    AND c.type_id = (SELECT id FROM lu_config_type WHERE type = 'Policy')
    AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='evidence_parent_create' LIMIT 1);

INSERT INTO
    `config`(
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
       'learning_moment_moderation_enabled',
       '0',
       'Enable Learning Moment Moderation',
       'If toggled to ‘ON’, the instance will use learning moment moderation based upon the year level configuration. Students without a year level will require moderation.', (
           SELECT `id`
           FROM
               `lu_config_field`
           WHERE
               `field` = 'boolean'
       ), (
           SELECT `id`
           FROM
               `lu_config_type`
           WHERE
               `type` = 'Policy'
       ),
       'true',
       'false',
       'true',
       'settings', (SELECT c2.sequence-1 FROM config c2 WHERE c2.name='evidence_parent_create' LIMIT 1)
   );
