UPDATE `config` SET `description`='This URL is used to take parents to the page where they can request a password reset. If this is blank, the forgotten password link will not display at all.' WHERE `name` like 'forgotten_password_url';
UPDATE `config` SET `description`='The text of the forgotten password link on the login page. If this is blank, the forgotten password link will not display at all.' WHERE `name` like 'forgotten_password_text';

