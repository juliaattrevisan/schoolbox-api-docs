-- Create user_qualification junction table for user-qualification relationships
-- This table will store the many-to-many relationship between users and qualifications as per SA-21623

CREATE TABLE `user_qualification` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `qualification_id` int(11) NOT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_qualification` (`user_id`, `qualification_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_qualification_id` (`qualification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;