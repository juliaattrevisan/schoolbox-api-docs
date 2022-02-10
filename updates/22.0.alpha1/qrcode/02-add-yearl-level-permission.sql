/** Add qrcode_enabled column to Year Level list
 */
ALTER TABLE lu_year_level
    ADD COLUMN qrcode_enabled TINYINT(1) DEFAULT 0 AFTER learning_moments_enabled;
