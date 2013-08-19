INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field`, `type`, `editable`, `hidden`, `required`)
  VALUES
  ('proxy_host', '', 'Proxy Host', 'The proxy server hostname or IP address', 'input', 'Security', 'true', 'false', 'false'),
  ('proxy_port', '', 'Proxy Port', 'The proxy server port', 'input', 'Security', 'true', 'false', 'false'),
  ('proxy_user', '', 'Proxy Username', 'The username Schoolbox uses for proxy authentication (can be blank for anonymous)', 'input', 'Security', 'true', 'false', 'false'),
  ('proxy_pass', '', 'Proxy Password', 'The password Schoolbox uses for proxy authentication (can be blank for anonymous)', 'input', 'Security', 'true', 'false', 'false');