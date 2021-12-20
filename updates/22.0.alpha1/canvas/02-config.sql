-- push everything down

UPDATE config
SET sequence = sequence+1
WHERE sequence > (SELECT sequence FROM config WHERE name = 'canvas_data_api_secret');

-- squeeze into the gap

INSERT INTO `config`
(
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
    'canvas_bearer_token',
    '',
    'Canvas Bearer Token',
    'The authorization bearer token for importing Canvas Course Announcements and Canvas Assignments component',
    '1',
    '7',
    'true',
    'true',
    'false',
    'settings',
    (SELECT sequence+1 FROm config c WHERE name = 'canvas_data_api_secret')
);
