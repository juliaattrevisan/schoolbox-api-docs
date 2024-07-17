DELETE FROM assessment_view WHERE participant_id IS NULL;
ALTER TABLE assessment_view MODIFY COLUMN participant_id int(4) unsigned NOT NULL COMMENT 'The user whose work was viewed.';
