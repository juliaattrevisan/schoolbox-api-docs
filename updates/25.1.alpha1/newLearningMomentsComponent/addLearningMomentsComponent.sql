INSERT INTO component_definition SET
name                     ='Learning Moments',
class                    ='Schoolbox\\Learning\\Component\\Homepage\\LearningMomentsController',
description              ='Displays methods for a student to create a learning moment.',
module                   ='homepage',
component_category       =3, # Links to table homepageComponentCategory.hompageComponentCategoryID.  3 is 'Learning'.
visible                  ='true',
multiple                 ='false',
msg_namespace_slug       =null,
msg_namespace_slug_suffix=null,
config_copy              =null;

INSERT INTO component_config_definition (
    component_definition_id,
    `key`,
    default_value,
    label,
    description
) VALUES (
    (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Learning\\Component\\Homepage\\LearningMomentsController'),
    'componentname',
    'Learning Moments',
    'Heading',
    null
), (
    (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Learning\\Component\\Homepage\\LearningMomentsController'),
    'collapse',
    0,
    'Collapse Content',
    null
), (
    (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Learning\\Component\\Homepage\\LearningMomentsController'),
    'showComponentTitle',
    1,
    'Show Title to Read-Only Users',
    null
);
