# Set to NULL so the component is properly sorted with the others

UPDATE component_definition
SET
    component_category = NULL
WHERE
    class = 'Schoolbox\\Learning\\Component\\Dashboard\\CurrentLessonPlanController'
    AND module = 'dashboard';
