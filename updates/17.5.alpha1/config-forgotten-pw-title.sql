/* Create a config option for forgotten password page title  */
INSERT INTO `config` (
  `name`,
  `title`,
  `value`,
  `description`,
  `field_id`,
  `type_id`,
  `editable`,
  `required`
) VALUES (
  'forgotten_password_title',
  'Forgotten Password Page Title',
  'Have You Forgotten Your Password?',
  'The title that will appear at the top of the forgotten password page.',
  1,
  (SELECT `id` FROM lu_config_type WHERE `type` = 'Policy'),
  true,
  true
);

/* Update both forgotten password page configs for Shore as per SA-4388 */
SELECT @sitelocation := `value` FROM `config` WHERE `name` = 'site_location';

UPDATE  `config`
SET     `value` = IF(@sitelocation = 'ss', 'To Create or Change your Password please enter you email address below.', value)
WHERE   `name` = 'forgotten_password_title';

UPDATE  `config`
SET     `value` = IF(@sitelocation = 'ss', 'A link will then be sent to you to set or reset your password. This feature works for Parents only.', value)
WHERE   `name` = 'forgotten_password_desc';