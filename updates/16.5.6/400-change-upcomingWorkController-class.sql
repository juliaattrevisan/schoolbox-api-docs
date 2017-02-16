-- Move the class so we can re-write in TWIG
UPDATE component_definition
SET class = 'Schoolbox\\Learning\\Component\\Dashboard\\UpcomingWorkController'
WHERE class = 'Component_Dashboard_UpcomingWorkController';
