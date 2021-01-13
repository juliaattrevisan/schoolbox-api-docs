ALTER TABLE evidence
    ADD COLUMN `type` ENUM ('photo','text','upload','video','audio') DEFAULT 'upload' AFTER `user_id`;
