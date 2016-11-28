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

-- Some providers for testing

INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('ChemVantage', 'schoolbox.edu', '14e75b9de5f8981e3b63a493d4bd7608', 'https://www.chemvantage.org/lti_config.xml', null, 'https://www.chemvantage.org/lti/', null, '2016-11-16 13:42:33', null, null);
INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('OneNote Class Notebook', 'f12d5cd9c2414c7b800141b603849a9d', '0181eb7a91fab7f9552ea2bf6aec3bff96a7ea7f', '', null, 'https://www.onenote.com/lti', null, '2016-11-24 10:14:26', null, null);
INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('Learning Field', '159f2820-b326-11e6-9598-0800200c9a66', '4b435c31622b5e656b7e504c4b6b405f', '', null, 'https://bc.vitalsource.com/books/book', null, '2016-11-28 14:49:25', null, null);
INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('Wikipedia', '', '', 'http://www.eduappcenter.com/configurations/oc8j7hxnzsej9h62.xml', '', 'https://www.edu-apps.org/tool_redirect?id=wikipedia', null, '2016-11-25 15:01:25', '2016-11-25 15:01:25', null);
INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('Trello', '', '', 'http://www.eduappcenter.com/configurations/ko92jqbkfivydwl4.xml', '', 'https://www.edu-apps.org/tool_redirect?id=trello', null, '2016-11-25 15:01:59', '2016-11-25 15:01:59', null);
INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('Programr', '', '', 'https://www.edu-apps.org/tools/programr/config.xml', '', 'https://www.edu-apps.org/tool_redirect?id=programr', null, '2016-11-25 15:02:48', '2016-11-25 15:02:48', null);
INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('Hooda Math', '', '', 'https://www.edu-apps.org/tools/hooda_math/config.xml', '', 'https://www.edu-apps.org/tool_redirect?id=hooda_math', null, '2016-11-25 15:03:06', '2016-11-25 15:03:06', null);