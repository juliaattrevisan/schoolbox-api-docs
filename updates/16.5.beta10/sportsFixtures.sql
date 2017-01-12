UPDATE `luServices` SET `name` = 'Fixtures Administrator' WHERE `luServices`.id = 10;

UPDATE component_definition SET `name` = 'Team Fixtures', description = 'This component will show a selected teams list of matches. It can show both past matches with results and upcoming matches.'
WHERE class='Schoolbox\\Fixtures\\Component\\Homepage\\Controller';

UPDATE component_definition SET `name` = 'My Fixtures', description = 'This component will show a users upcoming list of scheduled matches. It will not show if the user has no upcoming matches.'
WHERE class='Schoolbox\\Fixtures\\Component\\Dashboard\\Controller';