INSERT INTO component_definition (
    `name`,
    `class`,
    `description`,
    `module`,
    `component_category`,
    `enabled`,
    `visible`,
    `multiple`,
    `msg_namespace_slug`,
    `msg_namespace_slug_suffix`,
    `config_copy`
) VALUES (
    'Discussions',
    'Schoolbox\\Learning\\Assessment\\DialogAgent\\Component\\Homepage\\Controller',
    '', -- TODO
    'homepage',
    3, -- Learning
    1,
    'true',
    'false',
    null, -- TODO probably needs to be "dialogagent" and added to NSMapper I guess
    null, -- TODO similar to above... "#" I think as per Quizzes/Due Work components?
    null
);

INSERT INTO component_config_definition (
    `component_definition_id`,
    `key`,
    `default_value`,
    `label`,
    `description`
) VALUES
    (
        (SELECT `id` FROM component_definition WHERE `class` = 'Schoolbox\\Learning\\Assessment\\DialogAgent\\Component\\Homepage\\Controller'),
        'collapse',
        '0',
        'Collapse Content',
        NULL
    ),
    (
        (SELECT `id` FROM component_definition WHERE `class` = 'Schoolbox\\Learning\\Assessment\\DialogAgent\\Component\\Homepage\\Controller'),
        'compactMode',
        '1',
        'Compact Mode',
        NULL
    ),
    (
        (SELECT `id` FROM component_definition WHERE `class` = 'Schoolbox\\Learning\\Assessment\\DialogAgent\\Component\\Homepage\\Controller'),
        'componentname',
        'Discussions',
        'Heading',
        NULL
    ),
    (
        (SELECT `id` FROM component_definition WHERE `class` = 'Schoolbox\\Learning\\Assessment\\DialogAgent\\Component\\Homepage\\Controller'),
        'showClosed',
        '0',
        'Show Closed',
        'Whether to include closed discussions in the list'
    ),
    (
        (SELECT `id` FROM component_definition WHERE `class` = 'Schoolbox\\Learning\\Assessment\\DialogAgent\\Component\\Homepage\\Controller'),
        'showComponentTitle',
        '1',
        'Show Title to Read-Only Users',
        NULL
    ),
    (
        (SELECT `id` FROM component_definition WHERE `class` = 'Schoolbox\\Learning\\Assessment\\DialogAgent\\Component\\Homepage\\Controller'),
        'size',
        '0',
        'Limit',
        NULL
    );
