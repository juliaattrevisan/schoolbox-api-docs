-- Add a new config setting for alignment
INSERT INTO component_config_definition (
  component_definition_id,
  `key`,
  label,
  default_value
) VALUES (
(SELECT cd.id FROM component_definition cd WHERE cd.class='Component_Homepage_ScrollerController' LIMIT 1),
'align',
'Align',
'left'
);