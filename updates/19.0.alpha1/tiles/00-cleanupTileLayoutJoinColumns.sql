-- First make tile and tile layout IDs unsigned
ALTER TABLE tile CHANGE COLUMN id id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE tile DROP FOREIGN KEY tile_tile_layout;
ALTER TABLE tile CHANGE COLUMN tile_layout_id tile_layout_id INT(11) UNSIGNED NOT NULL;
ALTER TABLE tile_layout CHANGE COLUMN id id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;
-- This was a CASCADE: for consistency with our defacto standards it's now a NO ACTION
ALTER TABLE tile
    ADD CONSTRAINT tile_tile_layout
        FOREIGN KEY (tile_layout_id) REFERENCES tile_layout (`id`)
        ON DELETE NO ACTION
        ON UPDATE NO ACTION;


-- Make homepage templates unsigned
ALTER TABLE homepageTemplate CHANGE COLUMN id id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;


-- Make role ID unsigned, INT(11)
-- (Put in the too hard basket, given the amount of FKs on this column that would all need to be updated)
