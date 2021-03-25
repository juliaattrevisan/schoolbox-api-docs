ALTER TABLE storage_transform
    MODIFY `hash` VARCHAR(100) NOT NULL COLLATE 'ascii_general_ci', -- Remove nullability
    MODIFY `transform` VARCHAR(100) NOT NULL,                       -- Remove nullability
    ADD COLUMN state ENUM ('processing','done','failed'),
    ADD COLUMN updated_at DATETIME NOT NULL;

UPDATE storage_transform SET state = 'done', updated_at = NOW() WHERE TRUE;
