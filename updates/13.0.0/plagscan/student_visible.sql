/* Insert the config for whether or not students can see their own plagiarism score */
INSERT INTO
  `config` (`name`, `value`, `title`, `description`, `field`, `type`, `editable`)
VALUES
  ('plagscan_student_visible', '1', 'Students Plagscan Score Visible', 'Students can see their Plagscan score on submitted work', 'boolean', '3rd Party Services', 'true');