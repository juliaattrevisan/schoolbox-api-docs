UPDATE config
SET
    hidden = 'true',
    editable = 'false'
WHERE name IN (
               'paperly_iframe_api_host',
               'paperly_iframe_api_key',
               'paperly_agent_secret',
               'intellischool_jwt_private_key',
               'intellischool_deployment_id',
               'intellischool_deployment_secret'
    );
