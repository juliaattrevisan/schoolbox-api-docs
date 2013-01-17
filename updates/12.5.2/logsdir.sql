/* Create logs_dir for storing logs [SB-1781] */
INSERT INTO `config`
  (`name`, `value`, `title`, `description`, `type`, `required`)
VALUES
  ('logs_dir', '/var/www/schoolbox/data/cache/logs', 'Logs Cache Location', 'Location of log files', 'Paths', 'true');