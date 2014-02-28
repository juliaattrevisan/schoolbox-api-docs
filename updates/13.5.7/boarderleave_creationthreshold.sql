/* Create config option to define leave request creation threshold
 */
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`) VALUES ('boarder_leave_creation_threshold', '24', 'Boarder Leave Creation Threshold', 'Number of hours from Friday 12PM of the weekend leave is intended to commence. During this period leave requests cannot be created.', '1', '12', 'true');

/* Add error message
 */
INSERT INTO `error` (`id`, `message`) VALUES ('162', 'Leave request commences too soon.');