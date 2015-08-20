INSERT INTO `component_config_definition` (
    `component_definition_id`,
    `key`,
    `default_value`,
    `label`
) VALUES (
    (SELECT `id`
     FROM `component_definition`
     WHERE class = 'Schoolbox\\Tile\\Component\\DashboardTileController'
    ),
    'componentname',
    'Tiles',
    'Heading'
);
