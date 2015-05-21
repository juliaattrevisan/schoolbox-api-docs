/**
 * Tile background positions:
 * 7 8 9
 * 4 5 6
 * 1 2 3
 * (same as title position)
 */
ALTER TABLE tile
    ADD background_position TINYINT(1) NOT NULL DEFAULT 5 AFTER background_color;
