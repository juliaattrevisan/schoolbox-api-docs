INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label, description)
SELECT id, 'default', '0', 'Show ''All'' News Category By Default', NULL
FROM component_definition
WHERE class = 'Component_Dashboard_NewsController';
