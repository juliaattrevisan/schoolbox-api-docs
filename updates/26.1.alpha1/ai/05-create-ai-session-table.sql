-- Notes:
--  * Indexes have been added for the `feature` and `created_at` columns as well as `created_by` because I figure
--    all those columns will be commonly used in queries.
CREATE TABLE `ai_session` (
    `id` char(36) NOT NULL,
    `feature` enum('chat','lessonplan','image','differentiation','dialogagent','learningactivitycustomisation', 'aimoderationagent') NOT NULL,
    `created_at` datetime NOT NULL,
    `created_by` int(10) unsigned DEFAULT NULL COMMENT 'Null for system-created logs.',
    PRIMARY KEY (`id`),
    KEY `ai_session_feature_ix` (`feature`),
    KEY `ai_session_created_at_ix` (`created_at`),
    KEY `ai_session_created_by_fk` (`created_by`),
    CONSTRAINT `ai_session_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
