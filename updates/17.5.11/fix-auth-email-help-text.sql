UPDATE      `config`
SET         `description` = 'This allows users to log in with either a username or alternative email address. Please note that unexpected results may occur if the email address is not unique.'
WHERE       `name` = 'auth_email_enabled';