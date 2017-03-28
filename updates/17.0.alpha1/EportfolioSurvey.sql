INSERT INTO component_definition
(
  name,
  class,
  description,
  module,
  component_category,
  visible,
  multiple
) VALUES (
  'Survey Summary',
  'Schoolbox\\Survey\\Component\\Eportfolio\\Controller',
  'Provides a summary of latest survey results for a student on their ePortfolio page',
  'eportfolio',
  1,
  'true',
  'true'
);

INSERT INTO component_config_definition
(
  component_definition_id,
  `key`,
  default_value,
  label,
  description
) VALUES (
  (SELECT `id` FROM `component_definition` WHERE class = 'Schoolbox\\Survey\\Component\\Eportfolio\\Controller'),
  'collapse',
  '0',
  'Collapse Content',
  null
);

INSERT INTO component_config_definition
(
  component_definition_id,
  `key`,
  default_value,
  label,
  description
) VALUES (
  (SELECT `id` FROM `component_definition` WHERE class = 'Schoolbox\\Survey\\Component\\Eportfolio\\Controller'),
  'compactMode',
  '0',
  'Compact Mode',
  ''
);

INSERT INTO component_config_definition
(
  component_definition_id,
  `key`,
  default_value,
  label,
  description
) VALUES (
  (SELECT `id` FROM `component_definition` WHERE class = 'Schoolbox\\Survey\\Component\\Eportfolio\\Controller'),
  'componentname',
  'Survey',
  'Heading',
  null
);

INSERT INTO component_config_definition
(
  component_definition_id,
  `key`,
  default_value,
  label,
  description
) VALUES (
  (SELECT `id` FROM `component_definition` WHERE class = 'Schoolbox\\Survey\\Component\\Eportfolio\\Controller'),
  'src',
  '',
  'Source',
  null
);