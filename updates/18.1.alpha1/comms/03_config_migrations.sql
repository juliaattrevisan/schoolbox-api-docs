-- Default News Expiry Date from adminv2
-- This is now known as Expiry Date Offset in Comms Policy
UPDATE comms_news_policy SET `value` = (SELECT `value` FROM `config` WHERE name = 'news_default_expiry')
WHERE identifier = 'expiry_date_offset' LIMIT 1;

DELETE FROM `config` WHERE name = 'news_default_expiry' LIMIT 1;

-- Component: Dashboard
UPDATE `component_definition` SET `class`='Schoolbox\\Comms\\News\\Component\\Dashboard\\Controller'
WHERE `class` = 'Schoolbox\\News\\Component\\Dashboard\\Controller';
