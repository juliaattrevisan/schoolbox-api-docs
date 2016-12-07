INSERT INTO component_config_definition
(component_definition_id, `key`, default_value, label)
VALUES (
  (SELECT `id` FROM component_definition WHERE class = 'Component_Homepage_TextBoxController'),
  'componentname',
  'Text Box',
  'Heading'
);
