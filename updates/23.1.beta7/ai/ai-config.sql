INSERT INTO lu_config_type (type, sequence)
VALUES ('AI', 22);

INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `options`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
           'openai_endpoint',
           'OpenAI Endpoint Provider',
           'Select which provider you will use for conducting AI queries',
           (SELECT id FROM lu_config_field WHERE field = 'select'),
           (SELECT id FROM lu_config_type WHERE type = 'AI'),
           'OpenAI:Azure',
           'true',
           'false',
           'false',
           'settings',
           '1'
       );

UPDATE config SET
                  `type_id` = (SELECT id FROM lu_config_type WHERE type = 'AI'),
                  `description` = 'This is your secure key for authorising GPT based AI assistant services. The key can be created at https://openai.com/ or in Azure',
                  `sequence`=2
              WHERE name like 'openai_key';


INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
           'openai_azure_resource_name',
           'Azure OpenAI Resource Name',
           'The name of your Azure OpenAI Resource. Only required if using the Azure endpoint',
           (SELECT id FROM lu_config_field WHERE field = 'input'),
           (SELECT id FROM lu_config_type WHERE type = 'AI'),
           'true',
           'false',
           'false',
           'settings',
           '3'
       );

INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
           'openai_text_azure_deployment_id',
           'Azure OpenAI Deployment ID',
           'The deployment name you chose when you deployed the model in Azure.  Only required if using the Azure endpoint',
           (SELECT id FROM lu_config_field WHERE field = 'input'),
           (SELECT id FROM lu_config_type WHERE type = 'AI'),
           'true',
           'false',
           'false',
           'settings',
           '3'
       );

INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `options`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
           'openai_model',
           'OpenAI Model',
           'Select which model you wish to use for your OpenAI service.',
           (SELECT id FROM lu_config_field WHERE field = 'select'),
           (SELECT id FROM lu_config_type WHERE type = 'AI'),
           'gpt-3.5-turbo:gpt-4',
           'true',
           'false',
           'true',
           'settings',
           '4'
       );
