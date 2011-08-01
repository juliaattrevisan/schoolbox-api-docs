UPDATE `config`
SET `value` = '11.1.6' where `name` = 'site_version';

INSERT INGORE INTO `error` (`id`, `message`) VALUES('1','You are not logged in. You will need to <a href=\"login.php?page=%MESSAGE%\">login</a> before proceeding.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('2','Not enough information was submitted to the form.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('3','Please enter either a file or a url, not both.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('4','Your email could not be retrieved at this time.<br>If you have changed your email password recently, <a href=\"/mail/mailInfo.php\">click here</a> to update the email password stored on the portal.<br><br>Please contact your System Administrator if problems persist.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('5','You do not have permission to download this file.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('6','You do not have write permission to this category.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('7','Mail cannot be sent at this time. Please try again later.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('8','You do not have permission to delete this resource.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('9','Processing failed for the following reason: %MESSAGE%');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('10','The size of this file exceeds the allowed limit.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('11','%MESSAGE% is an illegal filetype.<br>You cannot add files of this type to the Portal.<br>If you would like to be able to add files of this type to the Portal please contact the System Administrator.<br>Ensure that you have closed the file before trying to submit it to the Portal.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('12','The category that you are trying to delete is not empty. Please delete all its contents, then try again.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('13','The category that you are trying to view does not exist.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('14','You do not have permission to view this category.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('15','The date that you have submitted is invalid. Please check it, then try again.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('16','There was a problem validating your account: <br><b>%MESSAGE%</b>');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('17','The submission box that you are trying to access does not exist or you do not have permission to view it.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('18','The subject selected does not exist.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('19','You do not have permission to view this page.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('20','You do not have permission to create a category in this location.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('21','A problem occurred while trying to access the database.<br>This could have been caused by one of the following reasons:<ul><li>one of the fields has invalid data</li><li>the database query is incorrect</li><li>the database is overloaded</li></ul><br>Please report this message to your System Administrator with the following query attached.<br><br>%MESSAGE%');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('22','The database is unavailable at this time. Please try again later.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('23','You do not have permission to modify this news.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('24','You are not enrolled in this subject.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('25','You do not have permission to return this work to this student.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('26','The work you are trying to return either does not exist or has already been returned.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('27','The work you are trying to submit has already been submitted.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('28','You do not have permission to post in this category.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('29','You do not have permission to modify or delete this post.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('30','This user cannot be the group administrator.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('31','No user was found that matches your request.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('32','The absentee system cannot be accessed on the weekend.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('33','That period does not exist today.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('34','You do not have permission to modify this event.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('35','You do not have permission to add an event of this context.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('36','You are attempting to submit work to a submission box that has not yet opened.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('37','You cannot join this group because it has membership restrictions. Contact the group administrator if you would like to be included.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('38','This is already in the database. Please modify, then try again.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('39','No teams exist. You must add teams before trying to add events.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('40','Attendances for Grimwade have not yet been implemented. Please wait until further notice.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('41','That file does not exist.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('42','No email account information was found. Please either use an external mail client to send email, or provide the portal with an email address.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('43','The complete file was not received by the server.  Please try to upload the file again.  If problems persist, contact your System Administrator.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('44','You are trying to view or delete work that either does not exist or does not belong to you.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('45','Your search does not have enough information to provide a realistic result.<br>Please make sure your search includes meaningful words over three letters long.<br>You can use quotation marks to search for specific strings of words, for example \"dog and cat\".');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('46','You have tried to read an email that does not exist in your mailbox.  Please recheck the email from <a href=\"/mail.php\">here</a>.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('101','The resource you are trying to access does not exist or you do not have permission to view it.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('102','A booking already exists during the time you have selected.  Please press back and select a time that is free.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('103','The booking you have made is outside the allowed range.  This resource may only be booked between the specified range. Please go back and select a time during this period.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('104','You do not have permission to update or modify this booking.  You must either have placed the booking yourself or be a room maintainer to update this booking.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('47','You do not have permission to modify this user\'s file.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('48','You do not have permission to delete this user\'s file.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('49','The folder you have attempted to retrieve does not contain any files.  As a result a zip file for download is not available.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('105','<p>An error occured while submitting the form:</p>\r\n<p>%MESSAGE%</p>');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('51','The section you are trying to add questions to does not exist.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('106','You do not have permission to modify or delete this link.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('107','You do not have permission to modify this blog.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('108','You do not have permission to leave comments on this blog.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('109','You do not have permission to publish this file.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('110','You do not have permission to modify these notes.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('111','There are no blogs in this archive.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('112','This assessment no longer exists.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('113','You have not answered all questions; please go back and submit them.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('114','You have not selected an attempt to review.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('116','Unspecified question type.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('117','Please choose a group for this assessment.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('118','This attempt no longer exists.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('119','This question no longer exists.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('120','This online assessment has expired.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('121','You have already attempted this online assessment and cannot re-sit it.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('122','You have run out of time to complete this online assessment and cannot re-sit it.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('123','This attempt has not been recorded.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('115','This section no longer exists.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('124','You are the owner of this test and therefore cannot attempt it.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('125','Only the test owner can view this page.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('126','Only teachers can create and modify tests.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('127','Only the test owner can review this test.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('128','This attempt no longer exists.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('129','You are not allowed to view this students test.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('130','Only teachers can create and modify their test contents.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('131','Question could not be saved.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('132','Answer could not be saved.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('133','Could not load question: unknown type.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('100','Your e-portfolio has not been enabled.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('134','No import file found.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('50','You do not have permission to delete this user\'s file.');
INSERT INGORE INTO `error` (`id`, `message`) VALUES('52','This link is already shared in your ePortfolio.');
INSERT IGNORE INTO `error` (`id`, `message`) VALUES('135','This is not a valid Assess Question XML.')

/* Added availability for questions and assessments/surveys
 */
ALTER TABLE `assess_luQuestionType` ADD COLUMN `availability` ENUM('assess','survey','all') NULL AFTER `automated_marking`;
UPDATE `assess_luQuestionType` SET `availability`='all' WHERE `type_id`='1';
UPDATE `assess_luQuestionType` SET `availability`='all' WHERE `type_id`='2';
UPDATE `assess_luQuestionType` SET `availability`='all' WHERE `type_id`='3';
UPDATE `assess_luQuestionType` SET `availability`='all' WHERE `type_id`='4';

ALTER TABLE `assess` ADD COLUMN `availability` ENUM('assess','survey') DEFAULT 'assess' NULL AFTER `allow_resit`;

/* Survey homepage component
 */
INSERT INTO `homepage_comp`(`id`,`size`,`method`,`name`,`description`,`homepageCategoryID`,`filename`,`config`,`invisible`,`allowMultiple`) VALUES ( '44','1','outputSurvey','Surveys','Online surveys.','3','survey.html','','false','false');

/* Whether or not exported responses for a question have a legend
 * Also applicable for View All Responses for a Survey question
 */
ALTER TABLE assess_luQuestionType ADD COLUMN has_legend ENUM('true', 'false') DEFAULT 'true';
UPDATE assess_luQuestionType SET has_legend='false' WHERE type_id='1';
