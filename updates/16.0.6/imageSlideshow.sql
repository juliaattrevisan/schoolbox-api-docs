INSERT INTO schoolbox.component_config_definition
(
  component_definition_id,
  `key`,
  default_value,
  label,
  description
)
VALUES
(
  (SELECT `id` FROM component_definition WHERE class = 'Component_Dashboard_ScrollerController'),
  'randomStartingSlide',
  '0',
  'Random start',
  null
);