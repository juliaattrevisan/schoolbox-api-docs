alter table tile_layout
    add column grid_padding TINYINT NOT NULL DEFAULT 0 AFTER grid_width,
    add column column_padding DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER column_count
;
