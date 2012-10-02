UPDATE `config`
SET `description` = 'By default display weather at this location. This value can be formatted as Country/City; latitude,longitude; airport code; or IP address.'
WHERE `name` = 'weather_location';