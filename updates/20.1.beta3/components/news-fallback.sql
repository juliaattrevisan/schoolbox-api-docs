-- Remove all Dashboard config options for itemImage

DELETE cci
FROM component_config_instance cci
         INNER JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
WHERE ccd.`key` IN ('itemImage');

DELETE FROM component_config_definition
WHERE `key` IN ('itemImage');

-- Insert them into the config area instead

INSERT INTO `config`
    (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES ('comms_news_article_list_layout',
        'list',
        'Layout',
        'Select layout format for articles in the News Feed',
        (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
        'list;List:list-reversed;List Reversed:card;Card',
        null,
        'true',
        'false',
        'false',
        'comms',
        (SELECT MAX(sequence) + 1 FROM `config` c WHERE `module` = 'comms')
        );

INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES ('comms_news_article_list_fallback_image',
        null,
        'Fallback Image',
        'Select fallback image for articles when a feature image is omitted',
        (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
        'author;Author Portrait:school-logo;School Logo',
        null,
        'true',
        'false',
        'false',
        'comms',
        (SELECT MAX(sequence) + 1 FROM `config` c WHERE `module` = 'comms')
       );
