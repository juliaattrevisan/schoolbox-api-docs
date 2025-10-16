CREATE TABLE differentiation_profiles_config
(
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(80)                   NOT NULL UNIQUE,
    title       VARCHAR(100)                  NOT NULL,
    terminology VARCHAR(100),
    description VARCHAR(256),
    is_enabled  TINYINT(1) unsigned DEFAULT 1 NOT NULL,
    module      VARCHAR(50)                   NOT NULL,
    sequence    INT                 DEFAULT 0
);

SET @adjustments_sequence = COALESCE((SELECT MAX(sequence) FROM differentiation_profiles_config WHERE module = 'adjustments'), 0);
SET @details_sequence = COALESCE((SELECT MAX(sequence) FROM differentiation_profiles_config WHERE module = 'details'), 0);

INSERT INTO differentiation_profiles_config (name, title, terminology, description, is_enabled, module, sequence)
VALUES
-- Adjustments Module
('adjustment_category', 'Adjustment Category', 'Adjustment Category', 'Category for Adjustment', 1, 'adjustments',
 @adjustments_sequence),
('adjustment_level', 'Adjustment Level', 'Adjustment Level', 'Level of adjustment required', 1, 'adjustments',
 @adjustments_sequence + 1),

-- Details Module
('overview', 'Overview', 'Overview', 'Concise overview of learner attributes essential to effective differentiation', 1,
 'details', @details_sequence),
('interests_strengths', 'Interests / Strengths', 'Interests / Strengths', 'List of students strengths and interests ',
 1, 'details', @details_sequence + 1),
('needs_challenges', 'Learning Needs / Challenges', 'Learning Needs', 'List of Needs/Challenges that the student faces',
 1, 'details', @details_sequence + 2),
('strategies_adjustments', 'Strategies / Adjustments', 'Specific Strategies',
 'Specific Strategies to consider when differentiating for the student', 1, 'details', @details_sequence + 3),
('goals', 'Goals', 'Learning Goals', 'Students individual learning goals', 1, 'details', @details_sequence + 4);
