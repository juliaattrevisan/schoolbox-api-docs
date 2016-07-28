-- The forgotten password email now contains a password reset link, not the actual password
UPDATE `config`
SET `options` = '0;Username and Password Reset Link:1;Password Reset Link Only'
WHERE `name` = 'forgotten_password';