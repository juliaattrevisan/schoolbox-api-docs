-- This field gets mapped to student_boarding_house in iterators

ALTER TABLE `sis_user_profile_student`
ADD COLUMN `boarding_house_name` VARCHAR(255) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' NULL AFTER `special_needs`;
