INSERT INTO
    component_config_definition (
        component_definition_id,
        `key`,
        default_value,
        label,
        description
    )
VALUES ( (
            SELECT id
            from
                component_definition
            where
                class = 'Component_Homepage_ScrollerController'
        ),
        'transition',
        'swipe',
        'Transition',
        null
    );

INSERT INTO
    component_config_definition (
        component_definition_id,
        `key`,
        default_value,
        label,
        description
    )
VALUES ( (
            SELECT id
            from
                component_definition
            where
                class = 'Component_Dashboard_ScrollerController'
        ),
        'transition',
        'swipe',
        'Transition',
        null
    );
