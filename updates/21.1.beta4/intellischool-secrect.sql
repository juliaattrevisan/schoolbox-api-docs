UPDATE config
SET hidden = 'false', sequence  = (
    SELECT MAX(sequence) + 1
    FROM config c
    WHERE type_id = (
        SELECT id
        FROM lu_config_type
        WHERE type = '3rd Party Services'
    )
)
WHERE name = 'intellischool_deployment_id';

INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required,
                              module, sequence)
VALUES ('intellischool_deployment_secret', null, 'Intellischool Deployment Secret',
        'The deployment secret for this instance\'s deployment of the Intellischool service', (SELECT id FROM lu_config_field WHERE field = 'password'),
        null, (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'), 'true', 'false', 'false', 'settings',
        (
        SELECT MAX(sequence) + 1
        FROM config c
        WHERE type_id = (
            SELECT id
            FROM lu_config_type
            WHERE type = '3rd Party Services'
        )
    ));

