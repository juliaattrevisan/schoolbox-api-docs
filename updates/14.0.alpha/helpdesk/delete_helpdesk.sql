DELETE component_definition, component_instance
FROM component_definition, component_instance
WHERE component_definition.id = component_instance.component_definition_id
      AND  (class like 'Component_Dashboard_HelpdeskController' OR class like 'Component_Dashboard_ParentHelpdeskController');


DELETE component_definition
FROM component_definition
WHERE (class like 'Component_Dashboard_HelpdeskController' OR class like 'Component_Dashboard_ParentHelpdeskController');
