CREATE TABLE `ib_group_subject_languages`
(
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `group_subject_id` INT(11) NOT NULL,
    `language_id` INT(11) NOT NULL,
    `system_purpose_id` INT(11) DEFAULT NULL
        COMMENT 'For Core subjects only: references ib_system_purposes to allow per-qualification-type language configuration',
    `system_purpose_unique_key` INT(11) GENERATED ALWAYS AS (COALESCE(`system_purpose_id`, 0)) VIRTUAL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ib_group_subject_languages_group_subject`
        FOREIGN KEY (`group_subject_id`) REFERENCES `ib_group_subjects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ib_group_subject_languages_language`
        FOREIGN KEY (`language_id`) REFERENCES `lu_language` (`id`),
    CONSTRAINT `fk_ib_gsl_system_purpose`
        FOREIGN KEY (`system_purpose_id`) REFERENCES `ib_system_purposes` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY `uniq_ib_group_subject_language` (`group_subject_id`, `language_id`, `system_purpose_unique_key`),
    KEY `idx_ib_group_subject_languages_group_subject_id` (`group_subject_id`),
    KEY `idx_ib_group_subject_languages_language_id` (`language_id`),
    KEY `idx_ib_group_subject_languages_system_purpose_id` (`system_purpose_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert English translations for all existing non-Core group subjects.
INSERT INTO `ib_group_subject_languages` (`group_subject_id`, `language_id`)
SELECT gs.`id`, l.`id`
FROM `ib_group_subjects` gs
INNER JOIN `lu_ib_groups` g
    ON g.`id` = gs.`group_id`
INNER JOIN `lu_language` l
    ON l.`code` = 'ENG'
WHERE g.`name` <> 'Core';

-- Insert English translations for Core group subjects per supported system purpose.
INSERT INTO `ib_group_subject_languages` (`group_subject_id`, `language_id`, `system_purpose_id`)
SELECT gs.`id`, l.`id`, sp.`id`
FROM `ib_group_subjects` gs
INNER JOIN `lu_ib_groups` g
    ON g.`id` = gs.`group_id`
INNER JOIN `lu_language` l
    ON l.`code` = 'ENG'
INNER JOIN `ib_system_purposes` sp
    ON sp.`name` IN ('Diploma Programme Year 2', 'Diploma Course')
INNER JOIN `lu_ib_category` c
    ON c.`id` = sp.`category_id`
WHERE g.`name` = 'Core'
    AND (
        (sp.`name` = 'Diploma Programme Year 2' AND c.`name` = 'DIPLOMA')
        OR (sp.`name` = 'Diploma Course' AND c.`name` = 'COURSE')
    );
