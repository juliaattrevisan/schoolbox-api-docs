/* Update default value of Course Criteria component from
 * "Rubric" to "Course Criteria"
 */
UPDATE component_config_definition
SET default_value = 'Course Criteria'
WHERE component_definition_id = (SELECT id FROM component_definition WHERE `name`='Course Criteria')
AND `key` = 'componentname';