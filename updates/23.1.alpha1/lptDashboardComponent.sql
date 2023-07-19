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
             'Schoolbox\\Learning\\Component\\Dashboard\\CurrentLessonPlanController',
             'Shows the current Lesson Plans for the user',
             'dashboard',
             3, # Learning
             'true',
             'false',
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
             (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Learning\\Component\\Dashboard\\CurrentLessonPlanController'),
             'range',
             0,
             'Visibility Range',
             null
#          commented out as the AC does not require additional config options...
#          ), (
#              (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Learning\\Component\\Dashboard\\CurrentLessonPlanController'),
#              'collapse',
#              0,
#              'Collapse Content',
#              null
#          ), (
#              (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Learning\\Component\\Dashboard\\CurrentLessonPlanController'),
#              'showComponentTitle',
#              1,
#              'Show Title to Read-Only Users',
#              null
#          ), (
#              (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Learning\\Component\\Dashboard\\CurrentLessonPlanController'),
#              'componentname',
#              'Lesson Plans',
#              'Heading',
#              null
         );
