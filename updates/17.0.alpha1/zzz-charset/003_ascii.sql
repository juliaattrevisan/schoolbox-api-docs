-- Columns that should not be utf8 at all (but are not hashes)
-- ascii is the only acceptable charset, assume that ascii_general_ci is fine
ALTER TABLE component_definition MODIFY class varchar(64) CHARACTER SET ascii COLLATE ascii_general_ci;
ALTER TABLE folder MODIFY colour varchar(32) CHARACTER SET ascii COLLATE ascii_general_ci;
ALTER TABLE mobile_app_registry MODIFY app_token varchar(4096) CHARACTER SET ascii COLLATE ascii_general_ci;
ALTER TABLE mobile_app_registry MODIFY device_id varchar(256) CHARACTER SET ascii COLLATE ascii_general_ci;
ALTER TABLE ups_custom_hydrator MODIFY class varchar(256) CHARACTER SET ascii COLLATE ascii_general_ci;
ALTER TABLE user_assume_log MODIFY ip varchar(255) CHARACTER SET ascii COLLATE ascii_general_ci;
ALTER TABLE user_password_reset_request MODIFY ip varchar(45) CHARACTER SET ascii COLLATE ascii_general_ci;
ALTER TABLE user_password_reset_request MODIFY token varchar(64) CHARACTER SET ascii COLLATE ascii_general_ci;

-- New columns added recently - assume class is ascii
ALTER TABLE filter MODIFY class varchar(255) CHARACTER SET ascii COLLATE ascii_general_ci;
