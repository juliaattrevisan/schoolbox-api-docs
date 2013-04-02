-- Fix all default definitions to be lowercase, if they are for ordering.
UPDATE component_config_definition
  SET default_value=LOWER(default_value)
  WHERE `key` IN ('sort', 'sortdir');