-- This is a clone of an earlier update script that was specific to Loyola college.
-- Added this so we don't have to jump through hoops to test the component in Vagrant

INSERT INTO component_definition
(
  name,
  class,
  description,
  module,
  component_category,
  visible,
  multiple
)
  SELECT
    'Survey Summary',
    'Custom\\demo\\Survey\\Component\\Eportfolio\\Controller',
    'Provides a summary of latest survey results for a student on their ePortfolio page',
    'eportfolio',
    1,
    'true',
    'true'
  FROM `config`
  WHERE `name` = 'site_location' and `value`= 'demo';

INSERT INTO component_config_definition
(
  component_definition_id,
  `key`,
  default_value,
  label,
  description
)
  SELECT
    (SELECT `id` FROM `component_definition` WHERE class = 'Custom\\demo\\Survey\\Component\\Eportfolio\\Controller'),
    'collapse',
    '1',
    'Display Component Heading On Student View',
    null
  FROM `config`
  WHERE `name` = 'site_location' and `value`= 'demo';

INSERT INTO component_config_definition
(
  component_definition_id,
  `key`,
  default_value,
  label,
  description
)
  SELECT
    (SELECT `id` FROM `component_definition` WHERE class = 'Custom\\demo\\Survey\\Component\\Eportfolio\\Controller'),
    'componentname',
    'Survey',
    'Heading',
    null
  FROM `config`
  WHERE `name` = 'site_location' and `value`= 'demo';

INSERT INTO component_config_definition
(
  component_definition_id,
  `key`,
  default_value,
  label,
  description
)
  SELECT
    (SELECT `id` FROM `component_definition` WHERE class = 'Custom\\demo\\Survey\\Component\\Eportfolio\\Controller'),
    'survey',
    '',
    'Select Survey',
    null
  FROM `config`
  WHERE `name` = 'site_location' and `value`= 'demo';

INSERT INTO component_config_definition
(
  component_definition_id,
  `key`,
  default_value,
  label,
  description
)

  SELECT
    (SELECT `id` FROM `component_definition` WHERE class = 'Custom\\demo\\Survey\\Component\\Eportfolio\\Controller'),
    'size',
    '4',
    'Number Of Questions To Display',
    null
  FROM `config`
  WHERE `name` = 'site_location' and `value`= 'demo';