INSERT INTO component_definition (
  name,
  class,
  description,
  module,
  component_category,
  visible,
  multiple
) VALUES (
  'Marketo',
  'Schoolbox\\Marketo\\Component\\Dashboard\\Controller',
  'Unavailable',
  'dashboard',
  NULL,
  'false',
  'false'
);

INSERT INTO component_config_definition (
  component_definition_id,
  `key`,
  default_value,
  label,
  description
) VALUES (
  (SELECT id from `component_definition` WHERE class='Schoolbox\\Marketo\\Component\\Dashboard\\Controller'),
  'description',
  'Marketo Client ID',
  'Marketo Client ID',
  NULL
);

INSERT INTO component_config_definition (
  component_definition_id,
  `key`,
  default_value,
  label,
  description
) VALUES (
  (SELECT id from `component_definition` WHERE class='Schoolbox\\Marketo\\Component\\Dashboard\\Controller'),
  'componentname',
  'Marketo API Key',
  'Marketo API Key',
  NULL
);