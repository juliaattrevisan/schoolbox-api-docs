INSERT INTO `config`
  (`name`, `value`, `title`, `description`, `field`, `type`,`editable`, `hidden`, `required`)
VALUES
  ('xml_cache_timeout', '3000', 'XML Update Frequency', 'How long xml data should be cached for before being re-queried in seconds', 'input', 'External DB', 'true', 'false', 'true');