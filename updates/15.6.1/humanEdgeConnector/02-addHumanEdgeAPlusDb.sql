-- These options should be made not hidden for HumanEdge schools
INSERT IGNORE INTO `config` (
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `options`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`
) VALUES (
    'external_humanedge_aplus_db_db',
    '',
    'HumanEdge A+ Database Name',
    'The database in which your A+ system data is stored',
    1, -- text
    '',
    7, -- external DB
    'true',
    'true',
    'false'
), (
    'external_humanedge_aplus_db_host',
    '',
    'HumanEdge A+ Database Host',
    'The host on which your A+ database is stored',
    1, -- text
    '',
    7, -- external DB
    'true',
    'true',
    'false'
), (
    'external_humanedge_aplus_db_user',
    '',
    'HumanEdge A+ Database User',
    'The username with which to connect to your A+ database',
    1, -- text
    '',
    7, -- external DB
    'true',
    'true',
    'false'
), (
    'external_humanedge_aplus_db_passwd',
    '',
    'HumanEdge A+ Database Password',
    'The password with which to connect to your A+ database',
    1, -- text
    '',
    7, -- external DB
    'true',
    'true',
    'false'
);
