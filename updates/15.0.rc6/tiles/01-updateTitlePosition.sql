/**
 * New background title positions:
 * 7 8 9
 * 4 5 6
 * 1 2 3
 *
 * Top (1) gets changed to top left (7) now, bottom (2) gets changed to bottom left (1)
 */
UPDATE tile SET title_position = 7 WHERE title_position = 1;
UPDATE tile SET title_position = 1 WHERE title_position = 2;
