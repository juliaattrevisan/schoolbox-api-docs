INSERT INTO `config`
(`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`)
VALUES
('feedback_url', '/feedback.php', 'Feedback URL', 'URL for the feedback link at the bottom of your Schoolbox instance.', 1, 1, true);

-- Update url for jpc
UPDATE    		`config` AS config1
INNER JOIN		`config` AS config2
				      ON config2.`value` = 'jpc' AND config2.`name` = 'site_location'
SET       		config1.`value` = 'https://web.jpcvictoria.catholic.edu.au/pp/Account/SupportEmail.aspx'
WHERE     		config1.`name` = 'feedback_url'

-- Empty url for stkevins, help
UPDATE    		`config` AS config1
INNER JOIN		`config` AS config2
				      ON config2.`value` IN ('stkevins', 'help') AND config2.`name` = 'site_location'
SET       		config1.`value` = ''
WHERE     		config1.`name` = 'feedback_url'