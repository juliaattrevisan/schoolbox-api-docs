/*
CREATE TABLE lti2_consumer (
  consumer_pk int(11) NOT NULL AUTO_INCREMENT,
  name varchar(50) NOT NULL,
  consumer_key256 varchar(256) NOT NULL,
  consumer_key text DEFAULT NULL,
  secret varchar(1024) NOT NULL,
  lti_version varchar(10) DEFAULT NULL,
  consumer_name varchar(255) DEFAULT NULL,
  consumer_version varchar(255) DEFAULT NULL,
  consumer_guid varchar(1024) DEFAULT NULL,
  profile text DEFAULT NULL,
  tool_proxy text DEFAULT NULL,
  settings text DEFAULT NULL,
  protected tinyint(1) NOT NULL,
  enabled tinyint(1) NOT NULL,
  enable_from datetime DEFAULT NULL,
  enable_until datetime DEFAULT NULL,
  last_access date DEFAULT NULL,
  created datetime NOT NULL,
  updated datetime NOT NULL,
  PRIMARY KEY (consumer_pk)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE lti2_consumer
  ADD UNIQUE INDEX lti2_consumer_consumer_key_UNIQUE (consumer_key256 ASC);

CREATE TABLE lti2_tool_proxy (
  tool_proxy_pk int(11) NOT NULL AUTO_INCREMENT,
  tool_proxy_id varchar(32) NOT NULL,
  consumer_pk int(11) NOT NULL,
  tool_proxy text NOT NULL,
  created datetime NOT NULL,
  updated datetime NOT NULL,
  PRIMARY KEY (tool_proxy_pk)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE lti2_tool_proxy
  ADD CONSTRAINT lti2_tool_proxy_lti2_consumer_FK1 FOREIGN KEY (consumer_pk)
REFERENCES lti2_consumer (consumer_pk);

ALTER TABLE lti2_tool_proxy
  ADD INDEX lti2_tool_proxy_consumer_id_IDX (consumer_pk ASC);

ALTER TABLE lti2_tool_proxy
  ADD UNIQUE INDEX lti2_tool_proxy_tool_proxy_id_UNIQUE (tool_proxy_id ASC);

CREATE TABLE lti2_nonce (
  consumer_pk int(11) NOT NULL,
  value varchar(32) NOT NULL,
  expires datetime NOT NULL,
  PRIMARY KEY (consumer_pk, value)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE lti2_nonce
  ADD CONSTRAINT lti2_nonce_lti2_consumer_FK1 FOREIGN KEY (consumer_pk)
REFERENCES lti2_consumer (consumer_pk);

CREATE TABLE lti2_context (
  context_pk int(11) NOT NULL AUTO_INCREMENT,
  consumer_pk int(11) NOT NULL,
  lti_context_id varchar(255) NOT NULL,
  settings text DEFAULT NULL,
  created datetime NOT NULL,
  updated datetime NOT NULL,
  PRIMARY KEY (context_pk)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE lti2_context
  ADD CONSTRAINT lti2_context_lti2_consumer_FK1 FOREIGN KEY (consumer_pk)
REFERENCES lti2_consumer (consumer_pk);

ALTER TABLE lti2_context
  ADD INDEX lti2_context_consumer_id_IDX (consumer_pk ASC);

CREATE TABLE lti2_resource_link (
  resource_link_pk int(11) AUTO_INCREMENT,
  context_pk int(11) DEFAULT NULL,
  consumer_pk int(11) DEFAULT NULL,
  lti_resource_link_id varchar(255) NOT NULL,
  settings text,
  primary_resource_link_pk int(11) DEFAULT NULL,
  share_approved tinyint(1) DEFAULT NULL,
  created datetime NOT NULL,
  updated datetime NOT NULL,
  PRIMARY KEY (resource_link_pk)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE lti2_resource_link
  ADD CONSTRAINT lti2_resource_link_lti2_context_FK1 FOREIGN KEY (context_pk)
REFERENCES lti2_context (context_pk);

ALTER TABLE lti2_resource_link
  ADD CONSTRAINT lti2_resource_link_lti2_resource_link_FK1 FOREIGN KEY (primary_resource_link_pk)
REFERENCES lti2_resource_link (resource_link_pk);

ALTER TABLE lti2_resource_link
  ADD INDEX lti2_resource_link_consumer_pk_IDX (consumer_pk ASC);

ALTER TABLE lti2_resource_link
  ADD INDEX lti2_resource_link_context_pk_IDX (context_pk ASC);

CREATE TABLE lti2_user_result (
  user_pk int(11) AUTO_INCREMENT,
  resource_link_pk int(11) NOT NULL,
  lti_user_id varchar(255) NOT NULL,
  lti_result_sourcedid varchar(1024) NOT NULL,
  created datetime NOT NULL,
  updated datetime NOT NULL,
  PRIMARY KEY (user_pk)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE lti2_user_result
  ADD CONSTRAINT lti2_user_result_lti2_resource_link_FK1 FOREIGN KEY (resource_link_pk)
REFERENCES lti2_resource_link (resource_link_pk);

ALTER TABLE lti2_user_result
  ADD INDEX lti2_user_result_resource_link_pk_IDX (resource_link_pk ASC);

CREATE TABLE lti2_share_key (
  share_key_id varchar(32) NOT NULL,
  resource_link_pk int(11) NOT NULL,
  auto_approve tinyint(1) NOT NULL,
  expires datetime NOT NULL,
  PRIMARY KEY (share_key_id)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE lti2_share_key
  ADD CONSTRAINT lti2_share_key_lti2_resource_link_FK1 FOREIGN KEY (resource_link_pk)
REFERENCES lti2_resource_link (resource_link_pk);

ALTER TABLE lti2_share_key
  ADD INDEX lti2_share_key_resource_link_pk_IDX (resource_link_pk ASC);
*/

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

