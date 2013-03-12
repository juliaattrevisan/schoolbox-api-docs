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
  'twitter_api_consumer_key',
  '',
  'Twitter API consumer key',
  'Consumer key for Twitter API.',
  'input',
  NULL,
  '3rd Party Services',
  'false',
  'false',
  'false'
),
(
  'twitter_api_consumer_secret',
  '',
  'Twitter API consumer secret',
  'Consumer secret for Twitter API.',
  'input',
  NULL,
  '3rd Party Services',
  'false',
  'false',
  'false'
),
(
  'twitter_api_oauth_token',
  '',
  'Twitter API OAuth token',
  'OAuth token for Twitter API.',
  'input',
  NULL,
  '3rd Party Services',
  'false',
  'false',
  'false'
),
(
  'twitter_api_oauth_token_secret',
  '',
  'Twitter API OAuth token secret',
  'OAuth token secret for Twitter API.',
  'input',
  NULL,
  '3rd Party Services',
  'false',
  'false',
  'false'
);