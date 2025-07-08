INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `options`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'openai_image_endpoint',
    'OpenAI Image Endpoint Provider',
    'Select which provider you will use for conducting AI image queries',
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
    'OpenAI Image Key',
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
    'Azure OpenAI Image Resource Name',
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
    'Azure OpenAI Image Deployment ID',
    'The deployment name you chose when you deployed the model in Azure. Only required if using the Azure endpoint',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    (SELECT id FROM lu_config_type WHERE type = 'AI'),
    'true',
    'false',
    'false',
    'openai-image',
    9
);
