ALTER TABLE rubric_capability_mark
ADD COLUMN human_readable_value VARCHAR(64) CHARSET 'utf8mb4' NULL
AFTER rubric_capability_id;
