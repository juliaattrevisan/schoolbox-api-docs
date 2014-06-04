-- Ensure that submission visibility max setting defaults to "staff, students and parents"
UPDATE `config` SET `value` = '2' WHERE `name` = 'submission_visibility_max' LIMIT 1;
