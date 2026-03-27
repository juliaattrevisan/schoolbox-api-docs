-- Add Reporting to filter settings for fresh Course Outline creations

UPDATE component_config_definition ccd
INNER JOIN component_definition cd ON ccd.component_definition_id = cd.id
AND cd.class = 'Schoolbox\\Learning\\Assessment\\Component\\Homepage\\Outline\\Controller'
SET
    ccd.`default_value` = '{"dueWork":"1","quiz":"1","task":"1","project":"1","lti":"1","lessonPlan":"1","dialogAgent":"1","reporting":"1"}'
WHERE
    ccd.`key` = 'assessmentTypes';
