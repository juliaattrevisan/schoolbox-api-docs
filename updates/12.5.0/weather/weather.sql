/* Set weather module cache directory in config table
 */
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field`, `options`, `type`, `editable`, `hidden`, `required`) VALUES ('weather_dir', '/var/www/schoolbox/data/cache/weather', 'Weather Cache Location', 'Location of Weather caching', 'input', NULL, 'Paths', 'false', 'false', 'true');
