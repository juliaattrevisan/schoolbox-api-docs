CREATE TABLE differentiation_profiles_config
(
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(80)                   NOT NULL,
    title       VARCHAR(100)                  NOT NULL,
    terminology VARCHAR(256),
    description TEXT,
    is_enabled  TINYINT(1) unsigned DEFAULT 1 NOT NULL,
    module      VARCHAR(50)                   NOT NULL,
    sequence    INT                 DEFAULT 0
);

SET @adjustments_sequence = (SELECT MAX(sequence) FROM differentiation_profiles_config WHERE module = 'adjustments');
SET @details_sequence = (SELECT MAX(sequence) FROM differentiation_profiles_config WHERE module = 'details');

INSERT INTO differentiation_profiles_config (name, title, terminology, description, is_enabled, module, sequence)
VALUES
-- Adjustments Module
('adjustment_category', 'Adjustment Category', null, null, 1, 'adjustments', @adjustments_sequence + 1),
('adjustment_level', 'Adjustment Level', null, null, 1, 'adjustments', @adjustments_sequence + 1),

-- Details Module
('summary', 'Summary', null, null, 1, 'details', @details_sequence + 1),
('needs_challenges', 'Needs / Challenges', null, null, 1, 'details', @details_sequence + 1),
('strategies_adjustments', 'Strategies / Adjustments', null, null, 1, 'details', @details_sequence + 1),
('goals', 'Goals', null, null, 1, 'details', @details_sequence + 1);
