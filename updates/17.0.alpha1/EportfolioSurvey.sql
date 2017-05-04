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
  '1',
  'Display Component Heading On Student View',
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
  'survey',
  '',
  'Select Survey',
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
  'size',
  '4',
  'Number Of Questions To Display',
  null
);