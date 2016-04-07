
INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label)
  SELECT cd.id, 'compactMode', 1, 'Hide heading from read only users'
  FROM component_definition cd WHERE class='Component_Homepage_BreadcrumbController';

INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label)
SELECT cd.id, 'compactMode', 0, 'Hide heading from read only users'
FROM component_definition cd WHERE class='Component_Homepage_TextBoxController';