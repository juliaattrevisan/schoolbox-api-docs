-- Add indices for user ID and class ID to this join table, otherwise the
-- timetable lookup is extremely slow.
ALTER TABLE `class_user`
ADD INDEX `class_user_user_id` (`user_id`),
ADD INDEX `class_user_class_id` (`class_id`);
