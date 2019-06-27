-- Remove old www/account/* stuff
DELETE FROM `config` WHERE `name` = 'mail_admin_address';
DELETE FROM `config` WHERE `name` = 'password_change_failed_email_subject';
