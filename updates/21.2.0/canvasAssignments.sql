INSERT INTO schoolbox.component_definition (name, class, description, module, component_category, enabled, visible,
                                            multiple, msg_namespace_slug, msg_namespace_slug_suffix, config_copy)
VALUES ('Canvas Assignments', 'Component_Dashboard_CanvasAssignmentsController', 'Canvas Assignments', 'dashboard',
        null, (SELECT COUNT(*) FROM config WHERE value = 'newic'), 'true', 'false', null, null, null);

#TODO: install to all schools, but only enable for NEWIC
