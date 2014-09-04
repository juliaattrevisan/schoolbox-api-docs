-- Tiles need to be in a certain order
ALTER TABLE `tile`
    ADD COLUMN `order` INT(11) DEFAULT 0 NOT NULL
    AFTER `background_color`;
