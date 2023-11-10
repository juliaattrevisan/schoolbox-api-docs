INSERT INTO
    config (
        name,
        value,
        title,
        description,
        field_id,
        type_id,
        editable,
        hidden,
        required,
        module
    )
VALUES (
        'learnosity_version',
        '2023.3.LTS',
        'Learnosity Version Number',
        'Version Number of Learnosity to use', (
            SELECT id
            FROM
                lu_config_field
            WHERE
                `field` = "input"
        ), (
            SELECT id
            FROM
                lu_config_type
            WHERE
                `type` = "3rd Party Services"
        ),
        'false',
        'true',
        'false',
        'settings'
    );
