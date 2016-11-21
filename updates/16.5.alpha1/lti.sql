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
  'Provider',
  'LTI Provider',
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
  created_at TIMESTAMP,
  updated_at DATETIME,
  deleted_at DATETIME
);

-- Some providers for testing

INSERT INTO lti_provider (name, `key`, secret, xml, url, created_at, updated_at, deleted_at) VALUES ('ChemVantage', 'schoolbox.edu', '14e75b9de5f8981e3b63a493d4bd7608', 'https://www.chemvantage.org/lti_config.xml', 'https://www.chemvantage.org/lti/', '2016-11-16 13:42:33', null, null);
INSERT INTO lti_provider (name, `key`, secret, xml, url, created_at, updated_at, deleted_at) VALUES ('Trello', null, null, 'http://www.eduappcenter.com/configurations/ko92jqbkfivydwl4.xml', 'https://www.edu-apps.org/tool_redirect?id=trello', '2016-11-16 13:50:48', null, null);
INSERT INTO lti_provider (name, `key`, secret, xml, url, created_at, updated_at, deleted_at) VALUES ('Programr', null, null, 'https://www.edu-apps.org/tools/programr/config.xml', 'https://www.edu-apps.org/tool_redirect?id=programr', '2016-11-16 14:32:47', null, null);
INSERT INTO lti_provider (name, `key`, secret, xml, url, created_at, updated_at, deleted_at) VALUES ('Internet Archive', null, null, 'http://www.eduappcenter.com/configurations/lyn1f978rtn9tm66.xml', 'https://www.edu-apps.org/tool_redirect?id=archive', '2016-11-16 14:01:13', null, null);
INSERT INTO lti_provider (name, `key`, secret, xml, url, created_at, updated_at, deleted_at) VALUES ('Nearpad', '377350aa168a23bc20c98f15ab94ca09', 'ccfc8e5c8483a1dcabb6f4e00c0e5c57', 'http://www.eduappcenter.com/configurations/bbv9it2jmtdpjdpc.xml', 'https://np1.nearpod.com/lti.php', '2016-11-16 14:22:26', null, null);