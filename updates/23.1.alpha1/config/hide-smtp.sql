UPDATE `config`
SET `hidden` = 'true'
WHERE `name` IN ('mail_smtp_host', 'mail_smtp_port', 'mail_smtp_username', 'mail_smtp_password');
