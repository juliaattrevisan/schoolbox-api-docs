CREATE TABLE differentiation_profiles
(
    id                     INT PRIMARY KEY AUTO_INCREMENT,
    student_id             INT(11) UNSIGNED NOT NULL,
    adjustment_category    int(11),
    adjustment_level       int(11),
    overview               TEXT,
    interests_strengths    TEXT,
    needs_challenges       TEXT,
    strategies_adjustments TEXT,
    goals                  TEXT,
    created_at datetime DEFAULT now() NOT NULL,
    updated_at datetime DEFAULT now() NOT NULL,
    deleted_at datetime               NULL
);

CREATE INDEX idx_student_id ON differentiation_profiles(student_id);

ALTER TABLE differentiation_profiles
    ADD CONSTRAINT fk_differentiation_profiles_student_id
        FOREIGN KEY (student_id) REFERENCES user (id);

CREATE INDEX idx_adjustment_level
    ON differentiation_profiles (adjustment_level);

ALTER TABLE differentiation_profiles
    ADD CONSTRAINT fk_differentiation_profiles_adjustment_level_id
        FOREIGN KEY (adjustment_level) REFERENCES differentiation_profiles_adjustment_level (id);

CREATE INDEX idx_adjustment_category
    ON differentiation_profiles (adjustment_category);

ALTER TABLE differentiation_profiles
    ADD CONSTRAINT fk_differentiation_profiles_adjustment_category_id
        FOREIGN KEY (adjustment_category) REFERENCES differentiation_profiles_adjustment_category (id);

