-- Create IB subject management tables for International Baccalaureate configuration

-- Table for IB Groups (Groups 1-6 + Core)
CREATE TABLE `lu_ib_groups` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Table for IB Subjects
CREATE TABLE `lu_ib_subjects` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Junction table for Group-Subject relationships with levels
CREATE TABLE `ib_group_subjects` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `group_id` int(11) NOT NULL,
    `subject_id` int(11) NOT NULL,
    `level` enum('HL','SL') DEFAULT NULL COMMENT 'Level is null for Core subjects',
    PRIMARY KEY (`id`),
    KEY `idx_group_id` (`group_id`),
    KEY `idx_subject_id` (`subject_id`),
    KEY `idx_level` (`level`),
    CONSTRAINT `fk_ib_group_subjects_group` FOREIGN KEY (`group_id`) REFERENCES `lu_ib_groups` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ib_group_subjects_subject` FOREIGN KEY (`subject_id`) REFERENCES `lu_ib_subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Insert IB Groups (Groups 1-6 + Core)
INSERT INTO `lu_ib_groups` (`name`) VALUES
('Studies in Language and Literature'),
('Language Acquisition'),
('Individuals and Societies'),
('Sciences'),
('Mathematics'),
('The Arts'),
('Core');

-- Insert IB Subjects
INSERT INTO `lu_ib_subjects` (`name`) VALUES
-- Group 1: Studies in Language and Literature
('Language A: literature'),
('Language A: language and literature'),
('Literature and performance'),

-- Group 2: Language Acquisition
('Classical languages'),
('Language Ab initio'),
('Language B'),

-- Group 3: Individuals and Societies
('Business management'),
('Digital society'),
('Economics'),
('Geography'),
('Global politics'),
('History'),
('History of Africa and the Middle East'),
('History of the Americas'),
('History of Asia and Oceania'),
('History of Europe'),
('Philosophy'),
('Psychology'),
('Social and cultural anthropology'),
('World religions'),

-- Group 4: Sciences
('Biology'),
('Chemistry'),
('Computer science'),
('Design technology'),
('Environmental systems and societies'),
('Physics'),
('Sports, exercise and health science'),

-- Group 5: Mathematics
('Analysis and approaches'),
('Applications and interpretation'),

-- Group 6: The Arts
('Dance'),
('Music'),
('Film'),
('Theatre'),
('Visual arts'),

-- Core Components (no levels for these)
('Theory of Knowledge (TOK)'),
('Extended Essay (EE)'),
('Creativity, Activity, Service (CAS)');

-- Insert Group-Subject relationships with levels
-- Get group IDs for reference
SET @studies_lit = (SELECT id FROM lu_ib_groups WHERE name = 'Studies in Language and Literature');
SET @lang_acq = (SELECT id FROM lu_ib_groups WHERE name = 'Language Acquisition');
SET @ind_soc = (SELECT id FROM lu_ib_groups WHERE name = 'Individuals and Societies');
SET @sciences = (SELECT id FROM lu_ib_groups WHERE name = 'Sciences');
SET @mathematics = (SELECT id FROM lu_ib_groups WHERE name = 'Mathematics');
SET @arts = (SELECT id FROM lu_ib_groups WHERE name = 'The Arts');

-- Group 1: Studies in Language and Literature
INSERT INTO `ib_group_subjects` (`group_id`, `subject_id`, `level`) VALUES
(@studies_lit, (SELECT id FROM lu_ib_subjects WHERE name = 'Language A: literature'), 'SL'),
(@studies_lit, (SELECT id FROM lu_ib_subjects WHERE name = 'Language A: literature'), 'HL'),
(@studies_lit, (SELECT id FROM lu_ib_subjects WHERE name = 'Language A: language and literature'), 'SL'),
(@studies_lit, (SELECT id FROM lu_ib_subjects WHERE name = 'Language A: language and literature'), 'HL'),
(@studies_lit, (SELECT id FROM lu_ib_subjects WHERE name = 'Literature and performance'), 'SL');

-- Group 2: Language Acquisition
INSERT INTO `ib_group_subjects` (`group_id`, `subject_id`, `level`) VALUES
(@lang_acq, (SELECT id FROM lu_ib_subjects WHERE name = 'Classical languages'), 'SL'),
(@lang_acq, (SELECT id FROM lu_ib_subjects WHERE name = 'Classical languages'), 'HL'),
(@lang_acq, (SELECT id FROM lu_ib_subjects WHERE name = 'Language Ab initio'), 'SL'),
(@lang_acq, (SELECT id FROM lu_ib_subjects WHERE name = 'Language B'), 'SL'),
(@lang_acq, (SELECT id FROM lu_ib_subjects WHERE name = 'Language B'), 'HL');

-- Group 3: Individuals and Societies
INSERT INTO `ib_group_subjects` (`group_id`, `subject_id`, `level`) VALUES
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'Business management'), 'SL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'Business management'), 'HL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'Digital society'), 'SL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'Digital society'), 'HL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'Economics'), 'SL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'Economics'), 'HL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'Geography'), 'SL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'Geography'), 'HL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'Global politics'), 'SL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'Global politics'), 'HL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'History'), 'SL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'History of Africa and the Middle East'), 'HL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'History of the Americas'), 'HL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'History of Asia and Oceania'), 'HL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'History of Europe'), 'HL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'Philosophy'), 'SL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'Philosophy'), 'HL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'Psychology'), 'SL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'Psychology'), 'HL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'Social and cultural anthropology'), 'SL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'Social and cultural anthropology'), 'HL'),
(@ind_soc, (SELECT id FROM lu_ib_subjects WHERE name = 'World religions'), 'SL');

-- Group 4: Sciences
INSERT INTO `ib_group_subjects` (`group_id`, `subject_id`, `level`) VALUES
(@sciences, (SELECT id FROM lu_ib_subjects WHERE name = 'Biology'), 'SL'),
(@sciences, (SELECT id FROM lu_ib_subjects WHERE name = 'Biology'), 'HL'),
(@sciences, (SELECT id FROM lu_ib_subjects WHERE name = 'Chemistry'), 'SL'),
(@sciences, (SELECT id FROM lu_ib_subjects WHERE name = 'Chemistry'), 'HL'),
(@sciences, (SELECT id FROM lu_ib_subjects WHERE name = 'Computer science'), 'SL'),
(@sciences, (SELECT id FROM lu_ib_subjects WHERE name = 'Computer science'), 'HL'),
(@sciences, (SELECT id FROM lu_ib_subjects WHERE name = 'Design technology'), 'SL'),
(@sciences, (SELECT id FROM lu_ib_subjects WHERE name = 'Design technology'), 'HL'),
(@sciences, (SELECT id FROM lu_ib_subjects WHERE name = 'Environmental systems and societies'), 'SL'),
(@sciences, (SELECT id FROM lu_ib_subjects WHERE name = 'Environmental systems and societies'), 'HL'),
(@sciences, (SELECT id FROM lu_ib_subjects WHERE name = 'Physics'), 'SL'),
(@sciences, (SELECT id FROM lu_ib_subjects WHERE name = 'Physics'), 'HL'),
(@sciences, (SELECT id FROM lu_ib_subjects WHERE name = 'Sports, exercise and health science'), 'SL'),
(@sciences, (SELECT id FROM lu_ib_subjects WHERE name = 'Sports, exercise and health science'), 'HL');

-- Group 5: Mathematics
INSERT INTO `ib_group_subjects` (`group_id`, `subject_id`, `level`) VALUES
(@mathematics, (SELECT id FROM lu_ib_subjects WHERE name = 'Analysis and approaches'), 'SL'),
(@mathematics, (SELECT id FROM lu_ib_subjects WHERE name = 'Analysis and approaches'), 'HL'),
(@mathematics, (SELECT id FROM lu_ib_subjects WHERE name = 'Applications and interpretation'), 'SL'),
(@mathematics, (SELECT id FROM lu_ib_subjects WHERE name = 'Applications and interpretation'), 'HL');

-- Group 6: The Arts
INSERT INTO `ib_group_subjects` (`group_id`, `subject_id`, `level`) VALUES
(@arts, (SELECT id FROM lu_ib_subjects WHERE name = 'Dance'), 'SL'),
(@arts, (SELECT id FROM lu_ib_subjects WHERE name = 'Dance'), 'HL'),
(@arts, (SELECT id FROM lu_ib_subjects WHERE name = 'Music'), 'SL'),
(@arts, (SELECT id FROM lu_ib_subjects WHERE name = 'Music'), 'HL'),
(@arts, (SELECT id FROM lu_ib_subjects WHERE name = 'Film'), 'SL'),
(@arts, (SELECT id FROM lu_ib_subjects WHERE name = 'Film'), 'HL'),
(@arts, (SELECT id FROM lu_ib_subjects WHERE name = 'Theatre'), 'SL'),
(@arts, (SELECT id FROM lu_ib_subjects WHERE name = 'Theatre'), 'HL'),
(@arts, (SELECT id FROM lu_ib_subjects WHERE name = 'Visual arts'), 'SL'),
(@arts, (SELECT id FROM lu_ib_subjects WHERE name = 'Visual arts'), 'HL');

-- Core Group (no levels for Core subjects)
SET @core = (SELECT id FROM lu_ib_groups WHERE name = 'Core');
INSERT INTO `ib_group_subjects` (`group_id`, `subject_id`, `level`) VALUES
(@core, (SELECT id FROM lu_ib_subjects WHERE name = 'Theory of Knowledge (TOK)'), NULL),
(@core, (SELECT id FROM lu_ib_subjects WHERE name = 'Extended Essay (EE)'), NULL),
(@core, (SELECT id FROM lu_ib_subjects WHERE name = 'Creativity, Activity, Service (CAS)'), NULL);
