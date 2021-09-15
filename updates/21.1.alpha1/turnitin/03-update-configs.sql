-- Rename plagscan_student_access to decouple implementation from abstraction
UPDATE `config` SET `name` = 'similarity_check_student_visible'
WHERE `name` = 'plagscan_student_visible';
