INSERT INTO `component_config_definition` (
    `component_definition_id`,
    `key`,
    `default_value`,
    `label`
) VALUES (
    (SELECT `id`
     FROM `component_definition`
     WHERE class = 'Schoolbox\\Tile\\Component\\HomepageTileController'
    ),
    'compactMode',
    TRUE,
    'Hide Heading from read-only users'
);
