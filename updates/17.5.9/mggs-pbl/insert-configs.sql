INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`) VALUES
    ('pbl_roles_enabled', '', 'Project Based Learning Roles Enabled', 'The roles enabled for use with Project Based Learning', '1', '15', 'false', 'true', 'false'),
    ('pbl_mastery_threshold', '70', 'Project Based Learning Mastery Threshold', 'Above this value, students are considered to have successfully mastered a task', '1', '15', 'false', 'true', 'false'),
    ('pbl_project_threshold', '50', 'Project Based Learning Project Threshold', 'Above this value, students are considered to have successfully completed a project', '1', '15', 'false', 'true', 'false')
ON DUPLICATE KEY UPDATE configID = configID;
