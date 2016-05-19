INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label)
  SELECT cd.id, 'imageRatio', 'square', 'Display Ratio'
  FROM component_definition cd WHERE class IN('Component_Homepage_ScrollerController','Component_Dashboard_ScrollerController') ;