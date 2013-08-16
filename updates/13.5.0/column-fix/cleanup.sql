-- Some templates were created with left width: 69.9 and right: 29.9
-- The max widths should be: 69.5, 29.5
UPDATE homepageTemplate
  SET   left_column_width = 69.5, right_column_width = 29.5
  WHERE left_column_width > 69.5;
UPDATE homepageTemplate
  SET   left_column_width = 29.5, right_column_width = 69.5
  WHERE left_column_width < 29.5;
UPDATE eportfolioTemplate
  SET   left_column_width = 69.5, right_column_width = 29.5
  WHERE left_column_width > 69.5;
UPDATE eportfolioTemplate
  SET   left_column_width = 29.5, right_column_width = 69.5
  WHERE left_column_width < 29.5;

UPDATE homepage_config
  SET   left_column_width = 69.5, right_column_width = 29.5
  WHERE left_column_width > 69.5;
UPDATE homepage_config
  SET   left_column_width = 29.5, right_column_width = 69.5
  WHERE left_column_width < 29.5;
UPDATE eportfolio_config
  SET   left_column_width = 69.5, right_column_width = 29.5
  WHERE left_column_width > 69.5;
UPDATE eportfolio_config
  SET   left_column_width = 29.5, right_column_width = 69.5
  WHERE left_column_width < 29.5;
