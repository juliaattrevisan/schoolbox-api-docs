INSERT INTO `config`(
  `name`,
  `value`,
  `title`,
  `description`,
  `field`,
  `options`,
  `type`,
  `editable`,
  `hidden`,
  `required`
)
VALUES (
  'video_processing_timeout',
  60*60,
  'Video Processing Timeout',
  'The maximum number of seconds that a video may process for before timing out and being marked as failed. (Set to 0 or less to disable timeout.)',
  'input',
  NULL,
  'Resources',
  'true',
  'false',
  'false'
);
