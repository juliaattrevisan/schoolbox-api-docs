-- Update configs to have a particular Module categorisation
-- for the admin ui

alter table config
    modify module enum ('settings', 'comms', 'custom', 'oauth', 'schoolData', 'lti', 'report-export', 'feature-tours', 'openai') default 'settings' null;

UPDATE config
SET module = 'openai',
type_id = null
WHERE `name` IN (
    'openai_azure_deployment_id',
    'openai_azure_resource_name',
    'openai_endpoint',
    'openai_key',
    'openai_model'
)
LIMIT 5;

-- For some random reason this sequence is wrong

UPDATE config
SET sequence = 5
WHERE `name` = 'openai_azure_deployment_id'
LIMIT 1;

-- gippity 3.5 is no longer allowed

UPDATE config
SET options='gpt-4o:gpt-4o-mini:gpt-4.1:gpt-4.1-mini'
WHERE `name` = 'openai_model'
LIMIT 1;


UPDATE config SET value = 'gpt-4.1-mini' WHERE `name` = 'openai_model' AND value IN('gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo') LIMIT 1;
