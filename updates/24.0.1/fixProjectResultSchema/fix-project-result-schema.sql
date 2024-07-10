#
# This script:
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


##
## 1. Deprecate project_id and remove indexes
##

# Remove indexes containing project_id
ALTER TABLE project_result DROP KEY project_id;
ALTER TABLE project_result DROP KEY project_id_user_id;
ALTER TABLE project_result DROP KEY project_id_user_id_marker_id;

# Deprecate project_id
ALTER TABLE project_result CHANGE project_id _project_id int(11) NOT NULL;


##
## 2. Create project_result.assessment_id, add foreign key, and re-create indexes
##

# Create project_result.assessment_id
ALTER TABLE project_result ADD COLUMN assessment_id int(10) unsigned NOT NULL AFTER id;
UPDATE project_result SET assessment_id = _project_id;

# Delete project_result rows without a matching assessment row prior to adding foreign key.
DELETE FROM project_result
WHERE assessment_id NOT IN (
    SELECT id
    FROM assessment
);

# Add foreign key.
ALTER TABLE project_result ADD CONSTRAINT fk_project_result_assessment_id FOREIGN KEY (assessment_id) REFERENCES assessment (id);

# Re-create indexes.
# (only need to recreate one of the three previously dropped, because one has been replaced by the foreign key, and one featured a deprecated column (_marker_id))
ALTER TABLE project_result ADD KEY idx_project_result_assessment_id_user_id (assessment_id, user_id);


#
# 3. Add foreign key for user_id column.
#

# First change column to match user.id.
ALTER TABLE project_result CHANGE COLUMN user_id user_id int(4) unsigned NOT NULL;

# Delete project_result rows without a matching user row prior to adding foreign key.
DELETE FROM project_result
WHERE user_id NOT IN (
    SELECT id
    FROM user
);

# Add foreign key.
ALTER TABLE project_result ADD CONSTRAINT fk_project_result_user_id FOREIGN KEY (user_id) REFERENCES user (id);


#
# Schema after:
#
# MariaDB [schoolbox]> show create table project_result\G
# *************************** 1. row ***************************
#        Table: project_result
# Create Table: CREATE TABLE `project_result` (
#   `id` int(11) NOT NULL AUTO_INCREMENT,
#   `assessment_id` int(10) unsigned NOT NULL,
#   `_project_id` int(11) NOT NULL,
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
