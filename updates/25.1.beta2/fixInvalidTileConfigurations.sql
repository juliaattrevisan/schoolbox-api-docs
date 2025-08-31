UPDATE tile_layout SET grid_width = 100 WHERE layout_type = 1 AND grid_width <= 0;
UPDATE tile_layout SET column_count = 3 WHERE layout_type = 2 AND column_count <= 0;
