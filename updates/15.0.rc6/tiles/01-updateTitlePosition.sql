/**
 * New background title positions:
 * 7 8 9
 * 4 5 6
 * 1 2 3
 *
 * Top (1) gets changed to 8 now, bottom (2) happens to be unchanged.
 */
UPDATE tile SET title_position = 8 WHERE title_position = 1;
