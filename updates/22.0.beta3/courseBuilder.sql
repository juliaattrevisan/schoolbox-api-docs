DELETE from component_config_instance
where component_config_definition_id = (SELECT id FROM component_config_definition
    WHERE
    component_definition_id = (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Learning\\Assessment\\Component\\Homepage\\Builder\\Controller')
AND
    label ='Show Title to Read-Only Users');

DELETE FROM component_config_definition
WHERE
    component_definition_id = (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Learning\\Assessment\\Component\\Homepage\\Builder\\Controller')
AND
    label ='Show Title to Read-Only Users';
