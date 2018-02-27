INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`)
VALUES ('academic_reports_wysiwyg', '', 'Academic Reports Page Info Box', 'Content provided here will be shown on the Academic Reports page', 8, '', 20, true, false, false);

# Only put content in if the school is PCI
SELECT @sitelocation := `value` FROM `config` WHERE `name` = 'site_location';

UPDATE `config`
SET value = IF(@sitelocation = 'pci', 'Click here to view reporting support documentation.<br\><a href="http://space.pc.wa.edu.au/homepage/3528">http://space.pc.wa.edu.au/homepage/3528</a>', value)
WHERE name = 'academic_reports_wysiwyg';