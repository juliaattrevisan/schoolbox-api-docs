UPDATE `config`
SET `hidden` = 'true'
WHERE `name` IN ('mail_smtp_host', 'mail_smtp_port');
