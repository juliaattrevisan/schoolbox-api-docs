ALTER TABLE login_log
ADD COLUMN identifier VARCHAR(36) CHARSET ascii COLLATE ascii_general_ci NULL
AFTER jwt_id;
