INSERT INTO `config` (
    `name`,
    `title`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    `sequence`
) VALUES (
    'intellischool_jwt_private_key',
    'Intellischool JWT Private Key',
    'The private key used to sign JWTs sent to the Intellischool service',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'true',
    'true',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM config c
        WHERE type_id = (
            SELECT id
            FROM lu_config_type
            WHERE type = '3rd Party Services'
        )
    )
), (
    'intellischool_jwt_private_key_signing_algorithm',
    'Intellischool JWT Private Key Signing Algorithm',
    'The signing algorithm for the Intellischool JWT private key',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'true',
    'true',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM config c
        WHERE type_id = (
            SELECT id
            FROM lu_config_type
            WHERE type = '3rd Party Services'
        )
    )
), (
    'intellischool_deployment_id',
    'Intellischool Deployment ID',
    'The deployment ID for this instance\'s deployment of the Intellischool service',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'true',
    'true',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM config c
        WHERE type_id = (
            SELECT id
            FROM lu_config_type
            WHERE type = '3rd Party Services'
        )
    )
), (
    'intellischool_learner_dashboard_base_url',
    'Intellischool Learner Dashboard Base URL',
    'The base URL for this instance\'s Intellischool learner dashboards',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'true',
    'true',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM config c
        WHERE type_id = (
            SELECT id
            FROM lu_config_type
            WHERE type = '3rd Party Services'
        )
    )
), (
    'intellischool_cohort_dashboard_base_url',
    'Intellischool Cohort Dashboard Base URL',
    'The base URL for this instance\'s Intellischool various cohort dashboards',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'true',
    'true',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM config c
        WHERE type_id = (
            SELECT id
            FROM lu_config_type
            WHERE type = '3rd Party Services'
        )
    )
);
