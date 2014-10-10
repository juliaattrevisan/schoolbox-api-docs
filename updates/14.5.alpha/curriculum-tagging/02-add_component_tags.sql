-- Add ability to change the tags for all homepage components
INSERT INTO component_config_definition
  (component_definition_id, `key`, default_value, label)
  SELECT id, 'tags', '', 'Tags'
  FROM component_definition
  WHERE module = 'homepage'
;
