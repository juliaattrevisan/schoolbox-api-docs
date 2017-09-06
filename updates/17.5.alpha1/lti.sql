-- Add new config field to LTI component definition

INSERT INTO `component_config_definition`
(
  `component_definition_id`,
  `key`,
  `default_value`,
  `label`
)
SELECT
  `id`,
  'url',
  '',
  'Embed URL'
FROM `component_definition`
WHERE `class` = 'Schoolbox\\LTI\\Component\\Homepage\\Controller';

-- Adding some sample providers to help schools get up and running with LTI more effectively


INSERT INTO `lti_provider` (`name`, `key`, `secret`, `xml`, `icon`, `url`, `custom`, `created_at`, `updated_at`, `deleted_at`) VALUES ('Khan','','','https://www.eduappcenter.com/configurations/rt6spjamqrgkduhr.xml','','https://www.edu-apps.org/lti_public_resources/?tool_id=khan_academy',NULL,NOW(),NOW(),NULL);
INSERT INTO `lti_provider` (`name`, `key`, `secret`, `xml`, `icon`, `url`, `custom`, `created_at`, `updated_at`, `deleted_at`) VALUES ('TEDEd','','','https://www.edu-apps.org/lti_public_resources/config.xml?id=youtube_ted_ed','','https://www.edu-apps.org/lti_public_resources/?tool_id=youtube_ted_ed',NULL,NOW(),NOW(),NULL);
INSERT INTO `lti_provider` (`name`, `key`, `secret`, `xml`, `icon`, `url`, `custom`, `created_at`, `updated_at`, `deleted_at`) VALUES ('Quizlet','','','https://www.eduappcenter.com/configurations/7mqdncyidtkbrig1.xml','','https://www.edu-apps.org/lti_public_resources/?tool_id=quizlet',NULL,NOW(),NOW(),NULL);
INSERT INTO `lti_provider` (`name`, `key`, `secret`, `xml`, `icon`, `url`, `custom`, `created_at`, `updated_at`, `deleted_at`) VALUES ('Vimeo','','','https://www.eduappcenter.com/configurations/7ebpoyrycqwqivxe.xml','','https://www.edu-apps.org/lti_public_resources/?tool_id=vimeo',NULL,NOW(),NOW(),NULL);
INSERT INTO `lti_provider` (`name`, `key`, `secret`, `xml`, `icon`, `url`, `custom`, `created_at`, `updated_at`, `deleted_at`) VALUES ('Youtube','','','https://www.edu-apps.org/lti_public_resources/config.xml?id=youtube','','https://www.edu-apps.org/lti_public_resources/?tool_id=youtube',NULL,NOW(),NOW(),NULL);
