#
# This script (in three parts, each in a separate file):
#  * Renames project_result.project_id to project_result.assessment_id
#    (but does so by deprecating the old column and adding the new column)
#    (reasons for doing this are given in updates/README.md)
#  * Adds foreign key constraints on project_result.assessment_id and project_result.user_id.
#

#
# Schema before:
#
# MariaDB [schoolbox]> show create table project_result\G
# *************************** 1. row ***************************
#        Table: project_result
# Create Table: CREATE TABLE `project_result` (
#   `id` int(11) NOT NULL AUTO_INCREMENT,
#   `project_id` int(11) NOT NULL,
#   `user_id` int(11) NOT NULL,
#   `_marker_id` int(11) DEFAULT NULL,
#   `mark` varchar(64) NOT NULL,
#   `norm_mark` decimal(14,11) NOT NULL,
#   `created_at` datetime NOT NULL,
#   `updated_at` datetime NOT NULL,
#   `deleted_at` datetime DEFAULT NULL,
#   PRIMARY KEY (`id`),
#   KEY `project_id` (`project_id`),
#   KEY `user_id` (`user_id`),
#   KEY `project_id_user_id` (`project_id`,`user_id`),
#   KEY `project_id_user_id_marker_id` (`project_id`,`user_id`,`_marker_id`)
# ) ENGINE=InnoDB AUTO_INCREMENT=235 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci


# Remove indexes containing project_id
ALTER TABLE project_result DROP KEY project_id;
ALTER TABLE project_result DROP KEY project_id_user_id;
ALTER TABLE project_result DROP KEY project_id_user_id_marker_id;

# Deprecate project_id
ALTER TABLE project_result CHANGE project_id _project_id int(11) NOT NULL;

# Create project_result.assessment_id
ALTER TABLE project_result ADD COLUMN assessment_id int(10) unsigned NOT NULL AFTER id;
UPDATE project_result SET assessment_id = _project_id;

# Delete project_result rows without a matching assessment row prior to adding foreign key.
DELETE FROM project_result
WHERE assessment_id NOT IN (
    SELECT id
    FROM assessment
);

# Continued in fix-project-result-schema-02.sql.
# The script is split here in case the UPDATE or DELETE commands above fail, which would cause any below commands not
# to run.  To minimise the chances of schema drift, we avoid having schema-altering commands after data-altering
# commands in the same update script.
