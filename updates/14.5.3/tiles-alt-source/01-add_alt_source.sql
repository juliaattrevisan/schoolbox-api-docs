-- Alt sources for tiles
INSERT INTO component_config_definition (
  component_definition_id,
  `key`,
  default_value,
  label
)
VALUES (
  (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Tile\\Component\\HomepageTileController'),
  'src',
  0,
  'Source'
);
INSERT INTO component_config_definition (
  component_definition_id,
  `key`,
  default_value,
  label
)
VALUES (
  (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Tile\\Component\\DashboardTileController'),
  'src',
  0,
  'Source'
);
-- selector for exact tile to show when an alt source is chosen for a tile component
INSERT INTO component_config_definition (
  component_definition_id,
  `key`,
  default_value,
  label
)
VALUES (
  (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Tile\\Component\\HomepageTileController'),
  'tileSource',
  0,
  'Tile'
);
INSERT INTO component_config_definition (
  component_definition_id,
  `key`,
  default_value,
  label
)
VALUES (
  (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Tile\\Component\\DashboardTileController'),
  'tileSource',
  0,
  'Tile'
);
