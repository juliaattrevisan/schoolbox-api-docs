INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `editable`, `type_id`) VALUES ('forgotten_password_url', '/forgotten-password.php', 'Forgotten Password Url', 'The url to visit when parents request their forgotten password', '1', 'true', 5);
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `editable`, `type_id`) VALUES ('forgotten_password_text', 'Parents, forgotten your password?', 'Forgotten Password Text', "The text contained within the link for the parents' forgotten password", '1', 'true', 5);

SELECT @sitelocation := `value` FROM `config` WHERE `name` = 'site_location';

/* Schools which have disabled or removed their Forgotten Password link entirely */

/* Donvale */
UPDATE `config` SET `value` = IF(@sitelocation = 'donvale', '', value) WHERE `name` = 'forgotten_password_url';
UPDATE `config` SET `value` = IF(@sitelocation = 'donvale', '', value) WHERE `name` = 'forgotten_password_text';

/* Grimwade */
UPDATE `config` SET `value` = IF(@sitelocation = 'grimwade', '', value) WHERE `name` = 'forgotten_password_url';
UPDATE `config` SET `value` = IF(@sitelocation = 'grimwade', '', value) WHERE `name` = 'forgotten_password_text';

/* Hutchins */
UPDATE `config` SET `value` = IF(@sitelocation = 'hutchins', '', value) WHERE `name` = 'forgotten_password_url';
UPDATE `config` SET `value` = IF(@sitelocation = 'hutchins', '', value) WHERE `name` = 'forgotten_password_text';

/* IGGS */
UPDATE `config` SET `value` = IF(@sitelocation = 'iggs', '', value) WHERE `name` = 'forgotten_password_url';
UPDATE `config` SET `value` = IF(@sitelocation = 'iggs', '', value) WHERE `name` = 'forgotten_password_text';

/* Kings */
UPDATE `config` SET `value` = IF(@sitelocation = 'kings', '', value) WHERE `name` = 'forgotten_password_url';
UPDATE `config` SET `value` = IF(@sitelocation = 'kings', '', value) WHERE `name` = 'forgotten_password_text';

/* Loreto */
UPDATE `config` SET `value` = IF(@sitelocation = 'loreto', '', value) WHERE `name` = 'forgotten_password_url';
UPDATE `config` SET `value` = IF(@sitelocation = 'loreto', '', value) WHERE `name` = 'forgotten_password_text';

/* Meriden */
UPDATE `config` SET `value` = IF(@sitelocation = 'meriden', '', value) WHERE `name` = 'forgotten_password_url';
UPDATE `config` SET `value` = IF(@sitelocation = 'meriden', '', value) WHERE `name` = 'forgotten_password_text';

/* MGS Staff */
UPDATE `config` SET `value` = IF(@sitelocation = 'mgs-staff', '', value) WHERE `name` = 'forgotten_password_url';
UPDATE `config` SET `value` = IF(@sitelocation = 'mgs-staff', '', value) WHERE `name` = 'forgotten_password_text';

/* SCC */
UPDATE `config` SET `value` = IF(@sitelocation = 'scc', '', value) WHERE `name` = 'forgotten_password_url';
UPDATE `config` SET `value` = IF(@sitelocation = 'scc', '', value) WHERE `name` = 'forgotten_password_text';

/* Spags */
UPDATE `config` SET `value` = IF(@sitelocation = 'spags', '', value) WHERE `name` = 'forgotten_password_url';
UPDATE `config` SET `value` = IF(@sitelocation = 'spags', '', value) WHERE `name` = 'forgotten_password_text';

/* St Kevins */
UPDATE `config` SET `value` = IF(@sitelocation = 'stkevins', '', value) WHERE `name` = 'forgotten_password_url';
UPDATE `config` SET `value` = IF(@sitelocation = 'stkevins', '', value) WHERE `name` = 'forgotten_password_text';

/* Wadhurst */
UPDATE `config` SET `value` = IF(@sitelocation = 'wadhurst', '', value) WHERE `name` = 'forgotten_password_url';
UPDATE `config` SET `value` = IF(@sitelocation = 'wadhurst', '', value) WHERE `name` = 'forgotten_password_text';

/* Schools which have custom setup */

/* Eltham */
UPDATE `config` SET `value` = IF(@sitelocation = 'eltham', 'https://webapp.elthamcollege.vic.edu.au/forgotpassword', value) WHERE `name` = 'forgotten_password_url';

/* Help */
UPDATE `config` SET `value` = IF(@sitelocation = 'help', 'Forgotten your username or password?', value) WHERE `name` = 'forgotten_password_text';

/* IGS */
UPDATE `config` SET `value` = IF(@sitelocation = 'igs', 'http://pwportal.ipswichgrammar.com:65535/', value) WHERE `name` = 'forgotten_password_url';
UPDATE `config` SET `value` = IF(@sitelocation = 'igs', 'Forgot your Password?', value) WHERE `name` = 'forgotten_password_text';

/* Pegs */
UPDATE `config` SET `value` = IF(@sitelocation = 'pegs', 'https://password.pegs.vic.edu.au/', value) WHERE `name` = 'forgotten_password_url';

/* Shore */
UPDATE `config` SET `value` = IF(@sitelocation = 'shore', 'Parents, for log in details and assistance please press here.', value) WHERE `name` = 'forgotten_password_text';

/* St Michaels */
UPDATE `config` SET `value` = IF(@sitelocation = 'stmichaels', 'https://services.stmichaels.vic.edu.au/preset.cfm', value) WHERE `name` = 'forgotten_password_url';

/* Tigs */
UPDATE `config` SET `value` = IF(@sitelocation = 'tigs', 'https://portal.tigs.nsw.edu.au/parentdetails.php', value) WHERE `name` = 'forgotten_password_url';

/* Wenona */
UPDATE `config` SET `value` = IF(@sitelocation = 'wenona', 'http://intranet.wenona.nsw.edu.au/ContentPages/ICT/AccountManagement/ForgotPassword/', value) WHERE `name` = 'forgotten_password_url';