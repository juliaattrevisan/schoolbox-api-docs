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
