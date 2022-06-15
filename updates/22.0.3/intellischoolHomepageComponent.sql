INSERT INTO component_definition (name, class, description, module, component_category, enabled, visible,
                                            multiple, msg_namespace_slug, msg_namespace_slug_suffix, config_copy)
VALUES ('Intellischool Launcher', 'Schoolbox\\Intellischool\\Component\\Homepage\\Controller',
        'Displays the Intellischool dashboard on homepages', 'homepage', 1, 1, 'false', 'false', null, null, null);

#all homepage components require at least 1 config value to function...
INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label, description)
VALUES ( (SELECT id from component_definition where class = 'Schoolbox\\Intellischool\\Component\\Homepage\\Controller')
        , 'componentname', 'Intellischool Launcher', 'Heading', null);
