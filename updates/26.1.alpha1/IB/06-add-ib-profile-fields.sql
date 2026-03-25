-- Add IB Qualification and IB Candidate Number to the profile editor field list as per SA-21578
INSERT INTO `profile_field` (`roletype`, `field`, `display_as`, `sequence`, `enabled`, `term`, `icon`, `css`, `options`, `options_default`, `is_external`)
VALUES
    ('student', 'qualificationCollection', 'Qualification', 9000, 0, 'Qualification', NULL, NULL, NULL, NULL, 0),
    ('student', 'ibCandidateNumber',       'IB Candidate Number', 9001, 0, NULL,            NULL, NULL, NULL, NULL, 0);
