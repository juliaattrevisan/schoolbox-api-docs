INSERT INTO `config`
  (`name`, `value`, `title`, `description`, `field`, `type`,`editable`, `hidden`, `required`)
VALUES
  ('show_all_subjects', '0', 'Show All Subjects on Profile', 'If enabled all enrolled subjects will be shown on student profile.  If disabled system will hide subjects that do not exist in Schoolbox', 'boolean', 'Policy', 'true', 'false', 'false');