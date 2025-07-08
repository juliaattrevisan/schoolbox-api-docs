-- Namespace text generator configs
-- The text ones are for little robot dude and lesson plan autogeneration

UPDATE config
SET name = 'openai_text_endpoint'
WHERE name = 'openai_endpoint'
LIMIT 1;

UPDATE config
SET title = 'OpenAI Text Endpoint Provider'
WHERE name = 'openai_text_endpoint'
LIMIT 1;

-- openai key
UPDATE config
SET name = 'openai_text_key'
WHERE name = 'openai_key'
LIMIT 1;

UPDATE config
SET title = 'OpenAI Text Key'
WHERE name = 'openai_text_key'
LIMIT 1;

-- openai model
UPDATE config
SET name = 'openai_text_model'
WHERE name = 'openai_model'
LIMIT 1;

UPDATE config
SET title = 'OpenAI Text Model'
WHERE name = 'openai_text_model'
LIMIT 1;

-- azure deployment id
UPDATE config
SET name = 'openai_text_azure_deployment_id'
WHERE name = 'openai_azure_deployment_id'
LIMIT 1;

UPDATE config
SET title = 'Azure OpenAI Text Deployment ID'
WHERE name = 'openai_text_azure_deployment_id'
LIMIT 1;

-- azure resource name
UPDATE config
SET name = 'openai_text_azure_resource_name'
WHERE name = 'openai_azure_resource_name'
LIMIT 1;

UPDATE config
SET title = 'Azure OpenAI Text Resource Name'
WHERE name = 'openai_text_azure_resource_name'
LIMIT 1;

-- Split text configs from their image counterparts
UPDATE config
SET module = 'openai-text'
WHERE module = 'openai'