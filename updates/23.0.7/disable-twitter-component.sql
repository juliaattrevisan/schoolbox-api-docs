UPDATE `component_definition`
SET `enabled` = 0,
    `visible` = 'false'
WHERE `class` IN
      (
       'Schoolbox\\Twitter\\Component\\Homepage\\Controller',
       'Schoolbox\\Twitter\\Component\\Dashboard\\Controller'
          );
