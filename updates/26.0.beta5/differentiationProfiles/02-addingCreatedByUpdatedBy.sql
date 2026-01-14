-- Add created_by and updated_by columns with indexes and foreign keys in a single ALTER TABLE
ALTER TABLE differentiation_profiles
    -- Add created_by and updated_by columns to differentiation_profiles table
    ADD created_by INT UNSIGNED NOT NULL AFTER created_at,
    ADD updated_by INT UNSIGNED NOT NULL AFTER updated_at,
    -- Create indexes on the new columns
    ADD INDEX idx_differentiation_profiles_created_by (created_by),
    ADD INDEX idx_differentiation_profiles_updated_by (updated_by),
    -- Add foreign key constraints to the new columns
    ADD CONSTRAINT fk_differentiation_profiles_created_by_user_id
        FOREIGN KEY (created_by) REFERENCES user (id),
    ADD CONSTRAINT fk_differentiation_profiles_updated_by_user_id
        FOREIGN KEY (updated_by) REFERENCES user (id);


