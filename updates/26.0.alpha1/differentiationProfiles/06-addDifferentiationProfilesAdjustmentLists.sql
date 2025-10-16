-- Add table for differentiation profiles adjustment categories
CREATE TABLE differentiation_profiles_adjustment_category
(
    id         INT PRIMARY KEY AUTO_INCREMENT,
    name       VARCHAR(255)           NOT NULL,
    created_at datetime DEFAULT now() NOT NULL,
    updated_at datetime DEFAULT now() NOT NULL,
    deleted_at datetime NULL
);



