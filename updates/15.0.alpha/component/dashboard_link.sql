-- Remove dashboard LinkController db stuff

DELETE FROM component_config_instance
WHERE component_config_definition_id IN (
    SELECT id
    FROM component_config_definition
    WHERE component_definition_id IN (
        SELECT id
        FROM component_definition
        WHERE class = 'Component_Dashboard_LinkController'
    )
);

DELETE FROM component_config_definition
WHERE component_definition_id IN (
    SELECT id
    FROM component_definition
    WHERE class = 'Component_Dashboard_LinkController'
);

DELETE FROM component_definition
WHERE class = 'Component_Dashboard_LinkController';
