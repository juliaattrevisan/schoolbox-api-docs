# Update the weather API key and description
UPDATE `config` SET `value`='93cc4486b7ba4afdafe44837182011', `description`='A key generated in the Apixu administration interface' WHERE name='weather_api_key';

# Add config for weather API endpoint - not editable because you can't just throw any API at the system and expect it to work
INSERT INTO `config`(`name`, `value`, `title`, `description`, `field_id`, `editable`, `hidden`, `required`, `module`) VALUES (
  'weather_api_url',
  'https://api.weatherstack.com',
  'Weather API URL',
  'Endpoint for weather API requests',
  '1',
  'false',
  'true',
  'true',
  'settings'
);
