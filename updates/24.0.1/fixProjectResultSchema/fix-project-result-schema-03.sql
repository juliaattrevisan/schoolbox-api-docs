#
# This script continues from fix-project-result-schema-02.sql.
#

# Add foreign key.
ALTER TABLE project_result ADD CONSTRAINT fk_project_result_user_id FOREIGN KEY (user_id) REFERENCES user (id);

#
# The command above completes the schema-fixing process.
# The project_result table schema should now look like this:
#
# MariaDB [schoolbox]> show create table project_result\G
# *************************** 1. row ***************************
#        Table: project_result
# Create Table: CREATE TABLE `project_result` (
#   `id` int(11) NOT NULL AUTO_INCREMENT,
#   `assessment_id` int(10) unsigned NOT NULL,
#   `_project_id` int(11) DEFAULT NULL,
#   `user_id` int(4) unsigned NOT NULL,
#   `_marker_id` int(11) DEFAULT NULL,
#   `mark` varchar(64) NOT NULL,
#   `norm_mark` decimal(14,11) NOT NULL,
#   `created_at` datetime NOT NULL,
#   `updated_at` datetime NOT NULL,
#   `deleted_at` datetime DEFAULT NULL,
#   PRIMARY KEY (`id`),
#   KEY `user_id` (`user_id`),
#   KEY `idx_project_result_assessment_id_user_id` (`assessment_id`,`user_id`),
#   CONSTRAINT `fk_project_result_assessment_id` FOREIGN KEY (`assessment_id`) REFERENCES `assessment` (`id`),
#   CONSTRAINT `fk_project_result_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
# ) ENGINE=InnoDB AUTO_INCREMENT=236 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
# 1 row in set (0.001 sec)
