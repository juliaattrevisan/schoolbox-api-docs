DELETE FROM
    component_config_definition
WHERE
    component_definition_id = (
        SELECT id
        FROM
            component_definition
        WHERE
            class = "Component_Dashboard_ImageSliderController"
    );

DELETE FROM
    component_definition
WHERE id = (
        SELECT id
        FROM
            component_definition
        WHERE
            class = "Component_Dashboard_ImageSliderController"
    );
