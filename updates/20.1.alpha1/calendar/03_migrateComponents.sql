UPDATE component_definition
SET class='Schoolbox\\Calendar\\Component\\Homepage\\Controller'
WHERE class = 'Component_Homepage_EventListController';
UPDATE component_definition
SET class='Schoolbox\\Calendar\\Component\\Dashboard\\Controller'
WHERE class = 'Component_Dashboard_CalendarController';
