#
# This script continues from fix-project-result-schema-01.sql.
#

# Add foreign key.
ALTER TABLE project_result ADD CONSTRAINT fk_project_result_assessment_id FOREIGN KEY (assessment_id) REFERENCES assessment (id);

# Re-create indexes.
# (only need to recreate one of the three previously dropped, because one has been replaced by the foreign key, and one featured a deprecated column (_marker_id))
ALTER TABLE project_result ADD KEY idx_project_result_assessment_id_user_id (assessment_id, user_id);

# Change project_result.user_id to match user.id.
ALTER TABLE project_result CHANGE COLUMN user_id user_id int(4) unsigned NOT NULL;

# Delete project_result rows without a matching user row prior to adding foreign key.
DELETE FROM project_result
WHERE user_id NOT IN (
    SELECT id
    FROM user
);

# Continued in fix-project-result-schema-03.sql.
# The script is split here in case the DELETE command above fails, which would cause any below commands not
# to run.  To minimise the chances of schema drift, we avoid having schema-altering commands after data-altering
# commands in the same update script.
