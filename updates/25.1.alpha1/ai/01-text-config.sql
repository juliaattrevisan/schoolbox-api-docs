-- Namespace text generator configs

update config
set module = 'settings'
where name in ('openai_endpoint',  'openai_key', 'openai_model', 'openai_azure_resource_name', 'openai_azure_deployment_id');

alter table config
    modify module enum ('settings', 'comms', 'custom', 'oauth', 'schoolData', 'lti', 'report-export', 'feature-tours', 'openai-text', 'openai-image') default 'settings';

-- The text ones are for little robot dude and lesson plan autogeneration

UPDATE config
SET name = 'openai_text_endpoint',
    module = 'openai-text',
    options = 'Off:OpenAI:Azure',
    required = 'true'
WHERE name = 'openai_endpoint'
LIMIT 1;

-- Force default "Off" if this was never activated
UPDATE config
SET value = 'Off'
WHERE name = 'openai_text_endpoint'
AND (`value` IS NULL OR `value` = '');

-- openai key
UPDATE config
SET name = 'openai_text_key',
    module = 'openai-text'
WHERE name = 'openai_key'
LIMIT 1;

-- openai model
UPDATE config
SET name = 'openai_text_model',
    module = 'openai-text'
WHERE name = 'openai_model'
LIMIT 1;

-- azure deployment id
UPDATE config
SET name = 'openai_text_azure_deployment_id',
    module = 'openai-text'
WHERE name = 'openai_azure_deployment_id'
LIMIT 1;

-- azure resource name
UPDATE config
SET name = 'openai_text_azure_resource_name',
    module = 'openai-text'
WHERE name = 'openai_azure_resource_name'
LIMIT 1;
