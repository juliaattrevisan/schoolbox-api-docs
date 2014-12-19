-- Alt sources for tiles
INSERT INTO component_config_definition (
  component_definition_id,
  `key`,
  default_value,
  label
)
VALUES (
  (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Tile\Component\\HomepageTileController'),
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
