-- Add table for differentiation profiles adjustment categories
CREATE TABLE differentiation_profiles_adjustment_category
(
    id         INT PRIMARY KEY AUTO_INCREMENT,
    name       VARCHAR(255)           NOT NULL,
    sequence   INT      DEFAULT 0,
    created_at datetime DEFAULT now() NOT NULL,
    updated_at datetime DEFAULT now() NOT NULL,
    deleted_at datetime               NULL
);

-- Insert default adjustment categories
INSERT INTO differentiation_profiles_adjustment_category (name, sequence)
VALUES
('Cognitive', 1),
('Physical', 2),
('Social / Emotional', 3),
('Sensory', 4);

-- Add table for differentiation profiles adjustment levels
CREATE TABLE differentiation_profiles_adjustment_level
(
    id         INT PRIMARY KEY AUTO_INCREMENT,
    name       VARCHAR(255)           NOT NULL,
    sequence   INT      DEFAULT 0,
    created_at datetime DEFAULT now() NOT NULL,
    updated_at datetime DEFAULT now() NOT NULL,
    deleted_at datetime               NULL
);

-- Insert default adjustment levels
INSERT INTO differentiation_profiles_adjustment_level (name, sequence)
VALUES
    ('Support', 1),
    ('Supplementary', 2),
    ('Substantial', 3),
    ('Extensive', 4);
