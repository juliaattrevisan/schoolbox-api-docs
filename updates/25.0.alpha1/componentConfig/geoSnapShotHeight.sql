INSERT INTO
    component_config_definition (
        component_definition_id,
        `key`,
        default_value,
        label,
        description
    )
VALUES (
        (
            SELECT id
            from component_definition
            where
                class = 'Schoolbox\\Geosnapshot\\Component\\Dashboard\\Controller'
        ),
        'height',
        500,
        'Height',
        'To customise the display height of the component'
    );

INSERT INTO
    component_config_definition (
        component_definition_id,
        `key`,
        default_value,
        label,
        description
    )
VALUES (
        (
            SELECT id
            from component_definition
            where
                class = 'Schoolbox\\Geosnapshot\\Component\\Homepage\\Controller'
        ),
        'height',
        500,
        'Height',
        'To customise the display height of the component'
    );
