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
  '300',
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
INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('Trello', null, null, 'http://www.eduappcenter.com/configurations/ko92jqbkfivydwl4.xml', null, 'https://www.edu-apps.org/tool_redirect?id=trello', null, '2016-11-24 11:20:08', null, '2016-11-24 11:20:08');
INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('Learning Field', 'bb004160-ad94-11e6-9598-0800200c9a66', '263f623d593b65384d29594e22206c22', '', null, 'https://bc.vitalsource.com/books/book', null, '2016-11-16 14:22:26', null, null);

-- INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('3D GameLab', '', '', 'https://www.eduappcenter.com/configurations/3omukxa9ansifpw9.xml', '', 'https://portal.3dgamelab.org/', null, '2016-11-24 11:31:33', '2016-11-24 11:27:05', '2016-11-24 11:31:33');
-- INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('Programr', null, null, 'https://www.edu-apps.org/tools/programr/config.xml', null, 'https://www.edu-apps.org/tool_redirect?id=programr', null, '2016-11-24 11:20:04', null, '2016-11-24 11:20:04');
-- INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('Internet Archive', null, null, 'http://www.eduappcenter.com/configurations/lyn1f978rtn9tm66.xml', null, 'https://www.edu-apps.org/tool_redirect?id=archive', null, '2016-11-24 11:20:11', null, '2016-11-24 11:20:11');
-- INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('Nearpad', '377350aa168a23bc20c98f15ab94ca09', 'ccfc8e5c8483a1dcabb6f4e00c0e5c57', 'http://www.eduappcenter.com/configurations/bbv9it2jmtdpjdpc.xml', null, 'https://np1.nearpod.com/lti.php', null, '2016-11-16 14:22:26', null, null);
-- INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('Edueto', '8efdaa1e519e61f92ec720470e4da8bb', 'e6ac8009acb4b20a65345a97e5cbfda2', 'https://www.eduappcenter.com/configurations/nje28v8znwcorf67.xml', '', 'https://www.edueto.com/lti', null, '2016-11-24 11:31:49', '2016-11-24 11:31:49', null);
-- INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('Portfolium', '', '', 'https://www.eduappcenter.com/configurations/7av9rj47yduvaj7o.xml', '', 'https://portfolium.com/lti/launch', null, '2016-11-24 11:38:02', '2016-11-24 11:38:02', null);
-- INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('Vai Response', '', '', 'https://services.viaresponse.com/lti/v1.0/config.xml', null, null, null, '2016-11-24 11:39:11', '2016-11-24 11:39:11', null);
-- INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('SchoolTube', '', '', 'https://www.eduappcenter.com/configurations/ea86pq04e8eth8tq.xml', '', 'https://www.edu-apps.org/lti_public_resources/?tool_id=schooltube', null, '2016-11-24 11:39:34', '2016-11-24 11:39:34', null);
-- INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('LTI Maps', '', '', 'https://bltools.creighton.edu/lti/ltimaps/config.xml', '', 'https://bltools.creighton.edu/lti/ltimaps/ltimaps/map.php ', null, '2016-11-24 11:48:36', '2016-11-24 11:48:36', null);
-- INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('TED Ed', '', '', 'https://www.edu-apps.org/lti_public_resources/config.xml?id=youtube_ted_ed', '', 'https://www.edu-apps.org/lti_public_resources/?tool_id=youtube_ted_ed', null, '2016-11-24 11:50:37', '2016-11-24 11:50:37', null);
-- INSERT INTO lti_provider (name, `key`, secret, xml, icon, url, custom, created_at, updated_at, deleted_at) VALUES ('Flinga', 'qE8fWSgRQmb2aX8PM', 'GHAFaqaSAGemMRiB4', 'https://www.eduappcenter.com/configurations/lvsk5fc25chdhbg.xml', '', 'http://demo.flinga.fi', null, '2016-11-24 11:54:33', '2016-11-24 11:52:47', null);

