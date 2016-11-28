INSERT INTO component_definition (
  name,
  class,
  description,
  module,
  component_category,
  visible,
  multiple
) VALUES (
  'LTI',
  'Schoolbox\\LTI\\Component\\Homepage\\Controller',
  'Learning Tools Interoperability',
  'homepage',
  3,
  'true',
  'true'
);

INSERT INTO component_config_definition (
  component_definition_id,
  `key`,
  default_value,
  label,
  description
) VALUES (
  (SELECT id from component_definition WHERE class = 'Schoolbox\\LTI\\Component\\Homepage\\Controller' LIMIT 1),
  'componentname',
  'Learning Activity',
  'Heading',
  null
);

INSERT INTO component_config_definition (
  component_definition_id,
  `key`,
  default_value,
  label,
  description
) VALUES (
  (SELECT id from component_definition WHERE class = 'Schoolbox\\LTI\\Component\\Homepage\\Controller' LIMIT 1),
  'ltiprovider',
  '',
  'LTI Provider',
  null
);

INSERT INTO component_config_definition (
  component_definition_id,
  `key`,
  default_value,
  label,
  description
) VALUES (
  (SELECT id from component_definition WHERE class = 'Schoolbox\\LTI\\Component\\Homepage\\Controller' LIMIT 1),
  'pixel',
  '500',
  'Component Height',
  null
);

INSERT INTO component_config_definition (
  component_definition_id,
  `key`,
  default_value,
  label,
  description
) VALUES (
  (SELECT id from component_definition WHERE class = 'Schoolbox\\LTI\\Component\\Homepage\\Controller' LIMIT 1),
  'description',
  '',
  'Custom Parameters',
  null
);

CREATE TABLE lti_provider
(
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255),
  `key` VARCHAR(255),
  secret VARCHAR(255),
  xml VARCHAR(2048),
  icon VARCHAR(255),
  url VARCHAR(255),
  custom TEXT,
  created_at TIMESTAMP,
  updated_at DATETIME,
  deleted_at DATETIME
);