INSERT INTO component_definition (
    name,
    class,
    description,
    module,
    component_category,
    visible,
    multiple,
    msg_namespace_slug,
    msg_namespace_slug_suffix,
    config_copy
) VALUES (
    'Student Toolkit',
    'Schoolbox\\Learning\\Moment\\StudentToolkit\\Component\\Dashboard\\Controller',
    'Module for student dashboards: displays their profile image and allows them to easily share work, set goals, see their achievements and see their activity.',
    'dashboard',
    null,
    'true',
    'false',
    null,
    null,
    null
);

-- This component no longer has a heading
# INSERT INTO component_config_definition (
#     component_definition_id,
#     `key`,
#     default_value,
#     label,
#     description
# ) VALUES (
#     (
#         SELECT id
#         FROM component_definition
#         WHERE class = 'Schoolbox\\Learning\\Moment\\StudentToolkit\\Component\\Dashboard\\Controller'
#         LIMIT 1
#     ),
#     'componentname',
#     'My Dashboard',
#     'Heading',
#     null
# );
