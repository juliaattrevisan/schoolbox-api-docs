-- Namespace text generator configs
-- The text ones are for little robot dude and lesson plan autogeneration

UPDATE config
SET name = 'openai_text_endpoint'
WHERE name = 'openai_endpoint'
LIMIT 1;

UPDATE config
SET name = 'openai_text_key'
WHERE name = 'openai_key'
LIMIT 1;

UPDATE config
SET name = 'openai_text_model'
WHERE name = 'openai_model'
LIMIT 1;

UPDATE config
SET name = 'openai_text_azure_deployment_id'
WHERE name = 'openai_azure_deployment_id'
LIMIT 1;

UPDATE config
SET name = 'openai_text_azure_resource_name'
WHERE name = 'openai_azure_resource_name'
LIMIT 1;
