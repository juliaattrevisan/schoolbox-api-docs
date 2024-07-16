-- This adds a new "range" field type to configs so that slideshow max can be restricted by a <input type="range"> element in the UI
INSERT INTO `lu_config_field` (`id`, `field`) VALUES (20, 'range');
