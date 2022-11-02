UPDATE `config`
SET `value` =
        (SELECT CONCAT(`value`, ' Password Reset') FROM `config` WHERE `name` = 'title')
WHERE `name` = 'password_change_email_subject';
