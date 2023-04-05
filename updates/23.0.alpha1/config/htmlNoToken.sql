-- Enable tokens for html field type
UPDATE `config` SET `options` = 'has-tokens' WHERE `name` = 'email_signature';
