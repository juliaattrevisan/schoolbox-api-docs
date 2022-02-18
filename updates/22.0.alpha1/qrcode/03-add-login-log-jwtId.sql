/** Add jwt_id column to login_log
 */
ALTER TABLE login_log
    ADD COLUMN `jwt_id` varchar(64) CHARACTER SET ascii DEFAULT NULL;
