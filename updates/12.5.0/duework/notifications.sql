/* Add fields to submission box table */
ALTER TABLE `submission_box`
	CHANGE `accept_submission` `accept_submission` tinyint(1)   NOT NULL DEFAULT '0' after `fid`

	/* these columns have already been deprecated,
	ADD COLUMN `notif_enabled` tinyint(1) unsigned   NOT NULL DEFAULT '0' after `max_mark`,
	ADD COLUMN `notif_sent` tinyint(1) unsigned   NOT NULL DEFAULT '0' after `notif_enabled`
	*/
	;

/* Add global enable/disable to config */
INSERT INTO `config` (
  `name`, `value`, `title`, `description`, `field`, `options`, `type`, `editable`, `hidden`, `required`
)
VALUES (
  'due_work_notifications_enabled', '1', 'Due Work Notifications Enabled',
  'If ticked, students can automatically be sent reminder emails regarding upcoming due work.',
  'boolean', 'true:false', 'Email', 'true', 'false', 'false'
);

