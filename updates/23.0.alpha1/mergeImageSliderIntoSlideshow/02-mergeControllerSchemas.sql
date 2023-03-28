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
            FROM
                component_definition
            WHERE
                class = 'Component_Dashboard_ScrollerController'
        ),
        'sort',
        'date',
        'Sort By',
        null
    ), ( (
            SELECT id
            FROM
                component_definition
            WHERE
                class = 'Component_Dashboard_ScrollerController'
        ),
        'sortdir',
        'desc',
        'Sort Direction',
        null
    ), ( (
            SELECT id
            FROM
                component_definition
            WHERE
                class = 'Component_Dashboard_ScrollerController'
        ),
        'pixel',
        '800',
        'Max Height',
        null
    ), ( (
            SELECT id
            FROM
                component_definition
            WHERE
                class = 'Component_Homepage_ScrollerController'
        ),
        'sort',
        'date',
        'Sort By',
        null
    ), ( (
            SELECT id
            FROM
                component_definition
            WHERE
                class = 'Component_Homepage_ScrollerController'
        ),
        'sortdir',
        'desc',
        'Sort Direction',
        null
    ), ( (
            SELECT id
            FROM
                component_definition
            WHERE
                class = 'Component_Homepage_ScrollerController'
        ),
        'pixel',
        '800',
        'Max Height',
        null
    );

;

UPDATE
    component_config_definition
SET default_value = 'auto'
WHERE
    component_definition_id = (
        SELECT id
        from
            component_definition
        where
            class = 'Component_Dashboard_ScrollerController'
    )
    AND `key` = 'imageRatio';

UPDATE
    component_config_definition
SET default_value = 'auto'
WHERE
    component_definition_id = (
        SELECT id
        from
            component_definition
        where
            class = 'Component_Homepage_ScrollerController'
    )
    AND `key` = 'imageRatio';

UPDATE component_definition
SET
    description = 'Display an animated image carousel'
WHERE
    class = 'Component_Dashboard_ScrollerController';

UPDATE component_definition
SET
    description = 'Display an animated image carousel'
WHERE
    class = 'Component_Homepage_ScrollerController';
