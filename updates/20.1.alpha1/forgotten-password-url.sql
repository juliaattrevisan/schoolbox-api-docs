-- forgotten-password.php is no longer a place you can visit
-- this has been replaced by /passwordReset

UPDATE `config`
SET `value` = '/passwordReset'
WHERE `name` = 'forgotten_password_url'
  AND `value` = '/forgotten-password.php';
