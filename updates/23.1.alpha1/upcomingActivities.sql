### `Upcoming Due Work` component now displayed as `Upcoming Learning Activities`
UPDATE component_definition
SET name = 'Upcoming Learning Activities'
WHERE class = 'Schoolbox\\Learning\\Component\\Dashboard\\UpcomingWorkController';


INSERT INTO component_config_definition (
    component_definition_id,
    `key`,
    default_value,
    label,
    description
) VALUES (
             (SELECT id FROM component_definition WHERE class = 'Schoolbox\\Learning\\Component\\Dashboard\\UpcomingWorkController'),
             'componentname',
             'Upcoming Learning Activities',
             'Heading',
             null
         );
