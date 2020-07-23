-- Add a new Component Config Definition to News Dashboard components
-- This lets you decide what the Card Layout should be

INSERT INTO
    component_config_definition (`component_definition_id`, `key`, `default_value`, `label`, `description`)
    (
        SELECT
            id, 'itemLayout', 'list', 'Layout', ''
        FROM component_definition
        WHERE `module` = 'dashboard'
          AND `class` = 'Schoolbox\\Comms\\News\\Component\\Dashboard\\Controller'
    );

-- This lets you decide what the avatar image should be
--
-- By default it'll try to use the article's specified Feature Image, but if one doesn't exist,
-- use an alternative specified by this config option instead

INSERT INTO
    component_config_definition (`component_definition_id`, `key`, `default_value`, `label`, `description`)
    (
        SELECT
            id, 'itemImage', 'feature-image', 'Fallback Image', ''
        FROM component_definition
        WHERE `module` = 'dashboard'
          AND `class` = 'Schoolbox\\Comms\\News\\Component\\Dashboard\\Controller'
    );
