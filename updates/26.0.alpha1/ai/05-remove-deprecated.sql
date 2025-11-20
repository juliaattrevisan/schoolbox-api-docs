-- The migration to json payload leaves orphaned configs that used the old naming convention

DELETE FROM config
WHERE name IN (
    'openai_image_azure_deployment_id',
    'openai_image_azure_resource_name',
    'openai_image_endpoint',
    'openai_image_key',
    'openai_image_model',
    'openai_image_output_quality',
    'openai_image_system_prompt',
    'openai_text_azure_deployment_id',
    'openai_text_azure_resource_name',
    'openai_text_endpoint',
    'openai_text_generation_system_prompt',
    'openai_text_key',
    'openai_text_model'
);

-- Consolidate openai-text and openai-image into ai_services

alter table config
    modify module enum ('settings', 'comms', 'custom', 'oauth', 'schoolData', 'lti', 'report-export', 'feature-tours', 'ai-services', 'openai-text', 'openai-image', 'differentiation-profiles') default 'settings' null;

UPDATE config
SET module = 'ai-services'
WHERE module IN ('openai-text', 'openai-image');

alter table config
    modify module enum ('settings', 'comms', 'custom', 'oauth', 'schoolData', 'lti', 'report-export', 'feature-tours', 'ai-services', 'differentiation-profiles') default 'settings' null;

