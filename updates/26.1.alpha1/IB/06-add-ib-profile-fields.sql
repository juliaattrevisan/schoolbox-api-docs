-- Add IB Qualification and IB Candidate Number to the profile editor field list as per SA-21578
INSERT INTO `profile_field` (`roletype`, `field`, `display_as`, `sequence`, `enabled`, `term`, `icon`, `css`, `options`, `options_default`, `is_external`)
SELECT
    'student', 'qualificationCollection', 'Qualification', COALESCE(MAX(`sequence`), 0) + 1, 0, 'Qualification', NULL, NULL, NULL, NULL, 0
FROM `profile_field`
WHERE `roletype` = 'student';

INSERT INTO `profile_field` (`roletype`, `field`, `display_as`, `sequence`, `enabled`, `term`, `icon`, `css`, `options`, `options_default`, `is_external`)
SELECT
    'student', 'ibCandidateNumber', 'IB Candidate Number', COALESCE(MAX(`sequence`), 0) + 1, 0, NULL, NULL, NULL, NULL, NULL, 0
FROM `profile_field`
WHERE `roletype` = 'student';
