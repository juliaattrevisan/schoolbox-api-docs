-- Namespace text generator configs

update config
set module = 'settings'
where name in ('openai_endpoint',  'openai_key', 'openai_model', 'openai_azure_resource_name', 'openai_azure_deployment_id');

alter table config
    modify module enum ('settings', 'comms', 'custom', 'oauth', 'schoolData', 'lti', 'report-export', 'feature-tours', 'openai-text', 'openai-image') default 'settings';

-- The text ones are for little robot dude and lesson plan autogeneration

UPDATE config
SET name = 'openai_text_endpoint',
    title = 'OpenAI Text Endpoint Provider',
    module = 'openai-text'
WHERE name = 'openai_endpoint'
LIMIT 1;

-- openai key
UPDATE config
SET name = 'openai_text_key',
    title = 'OpenAI Text Key',
    module = 'openai-text'
WHERE name = 'openai_key'
LIMIT 1;

-- openai model
UPDATE config
SET name = 'openai_text_model',
    title = 'OpenAI Text Model',
    module = 'openai-text'
WHERE name = 'openai_model'
LIMIT 1;

-- azure deployment id
UPDATE config
SET name = 'openai_text_azure_deployment_id',
    title = 'Azure OpenAI Text Deployment ID',
    module = 'openai-text'
WHERE name = 'openai_azure_deployment_id'
LIMIT 1;

-- azure resource name
UPDATE config
SET name = 'openai_text_azure_resource_name',
    title = 'Azure OpenAI Text Resource Name',
    module = 'openai-text'
WHERE name = 'openai_azure_resource_name'
LIMIT 1;
