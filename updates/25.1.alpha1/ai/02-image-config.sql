INSERT INTO config (`name`, `value`, `title`, `description`, `field_id`,  `type_id`, `options`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'openai_image_endpoint',
    'Off',
    'Image Generation Endpoint',
    'Select which provider you will use for conducting AI image queries; choose "Off" to disable this feature.',
    (SELECT id FROM lu_config_field WHERE field = 'select'),
    (SELECT id FROM lu_config_type WHERE type = 'AI'),
    'Off:OpenAI:Azure',
    'true',
    'false',
    'true',
    'openai-image',
    6
);

INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'openai_image_key',
    'Image Generation API Key',
    'This key allows GPT based AI assistant services to be provided when creating content. The key can be created at https://openai.com/',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    (SELECT id FROM lu_config_type WHERE type = 'AI'),
    'true',
    'false',
    'false',
    'openai-image',
    7
);

INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'openai_image_azure_resource_name',
    'Image Generation Azure Resource Name',
    'The name of your Azure OpenAI Resource. Only required if using the Azure endpoint',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    (SELECT id FROM lu_config_type WHERE type = 'AI'),
    'true',
    'false',
    'false',
    'openai-image',
    8
);

INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'openai_image_azure_deployment_id',
    'Image Generation Azure Deployment ID',
    'The deployment name you chose when you deployed the model in Azure. Only required if using the Azure endpoint',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    (SELECT id FROM lu_config_type WHERE type = 'AI'),
    'true',
    'false',
    'false',
    'openai-image',
    9
);

INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `options`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'openai_image_model',
    'Image Generation Model',
    'Select which model you wish to use for your OpenAI service.',
    (SELECT id FROM lu_config_field WHERE field = 'select'),
    (SELECT id FROM lu_config_type WHERE type = 'AI'),
    'gpt-4o:gpt-4o-mini:gpt-4.1:gpt-4.1-mini',
    'true',
    'false',
    'false',
    'openai-image',
    10
);

-- Special system prompt thing

INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'openai_image_system_prompt',
    'Image Generation System Prompt',
    'Provide some additional instructions to shape what the AI service generates for all image generation requests.',
    (SELECT id FROM lu_config_field WHERE field = 'textarea'),
    (SELECT id FROM lu_config_type WHERE type = 'AI'),
    'true',
    'false',
    'false',
    'openai-image',
    11
);
