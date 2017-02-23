-- Hydrator for giving parents their student's classes
INSERT INTO `ups_custom_hydrator` (
`class`,
`role_type`,
`enabled`,
`sequence`
) VALUES (
'Schoolbox\\Sync\\Hydrator\\Custom\\AddParentToStudentClasses',
'parent',
FALSE,
(SELECT COALESCE(MAX(`ch`.`sequence`), -1)+1 FROM `ups_custom_hydrator` `ch` WHERE `role_type` = 'parent')
);