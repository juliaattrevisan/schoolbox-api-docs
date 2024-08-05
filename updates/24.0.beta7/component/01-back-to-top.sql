INSERT INTO component_config_definition (
    `component_definition_id`,
    `key`,
    `default_value`,
    `label`,
    `description`
) VALUES (
    (SELECT id FROM component_definition WHERE `class` = 'Schoolbox\\Resource\\Textbox\\Component\\Homepage\\Controller'),
    'showBackToTop',
    0,
    'Show Back To Top Link',
    'Provide a "Back To Top" link on a per-component basis which sends users to the top of the current page.'
);