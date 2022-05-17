#delete all component instance config for the Course Builder
DELETE component_config_instance FROM component_config_instance
          JOIN component_config_definition on component_config_instance.component_config_definition_id = component_config_definition.id
         JOIN component_definition on component_config_definition.component_definition_id = component_definition.id
WHERE
        component_definition.class = 'Schoolbox\\Learning\\Assessment\\Component\\Homepage\\Builder\\Controller'
AND
        component_config_definition.key ='showComponentTitle';

#delete the base config for the Course Builder
DELETE component_config_definition FROM component_config_definition
         JOIN component_definition on component_config_definition.component_definition_id = component_definition.id
WHERE
    component_definition.class = 'Schoolbox\\Learning\\Assessment\\Component\\Homepage\\Builder\\Controller'
AND
        component_config_definition.key ='showComponentTitle';
