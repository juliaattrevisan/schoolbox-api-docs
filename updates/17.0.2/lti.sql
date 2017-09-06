-- Add new config field to LTI component definition

INSERT INTO `component_config_definition`
(
  `component_definition_id`,
  `key`,
  `default_value`,
  `label`
)
SELECT
  `id`,
  'url',
  '',
  'Embed URL'
FROM `component_definition`
WHERE `class` = 'Schoolbox\\LTI\\Component\\Homepage\\Controller';