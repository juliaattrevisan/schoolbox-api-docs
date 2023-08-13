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
             'Lesson Plans',
             'Schoolbox\\Learning\\Component\\Homepage\\CurrentLessonPlanController',
             'Shows the current lesson plans for the user',
             'homepage',
             3, # Learning
             'true',
             'true',
             null,
             null,
             null
         );
INSERT INTO component_config_definition (
    component_definition_id,
    `key`,
    default_value,
    label,
    description
) VALUES (
             (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Learning\\Component\\Homepage\\CurrentLessonPlanController'),
             'range',
             0,
             'Display Range',
             null
         ), (
             (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Learning\\Component\\Homepage\\CurrentLessonPlanController'),
             'collapse',
             0,
             'Collapse Content',
             null
         ), (
             (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Learning\\Component\\Homepage\\CurrentLessonPlanController'),
             'showComponentTitle',
             1,
             'Show Title to Read-Only Users',
             null
         ), (
             (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Learning\\Component\\Homepage\\CurrentLessonPlanController'),
             'componentname',
             'Lesson Plans',
             'Heading',
             null
         );
