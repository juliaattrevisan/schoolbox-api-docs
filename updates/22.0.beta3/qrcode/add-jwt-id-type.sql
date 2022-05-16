ALTER TABLE user_jwt_id ADD COLUMN `type` enum('api', 'qrcode') DEFAULT NULL AFTER user_id;
