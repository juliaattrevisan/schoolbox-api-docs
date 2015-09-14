<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/

//create weather

if(count($this->arg)) {
    $loc = str_replace('_',' ',implode('/', $this->arg));
}
else {
    $loc = Cfg::get('weather_location');
}

$weatherController = new Weather_Wunderground();
$weatherController->guessLocation($loc);
$weather = $weatherController->getForecast();

$this->setFile(TEMPLATE_content, 'weather/weather.html');
$this->setTitle('Weather Forecast');

$this->setBlock(TEMPLATE_content, 'current', 'current_block');
$this->setBlock(TEMPLATE_content, 'forecast', 'forecast_block');
$this->setBlock('forecast', 'forecast_day', 'forecast_day_block');
$this->setBlock(TEMPLATE_content, "weather_unavailable", "weather_unavailable_block");

$this->setVar("current_block", "");
$this->setVar("forecast_block", "");
$this->setVar("weather_unavailable_block", "");

$imageLocation = '/weather/image.php?path=';

$forecastDate = new Alaress_Date_Format($weather['current']['datetime'], 'U');

$wind = $weather['current']['wind']['speed']
    ? sprintf("%0.1fkm/h %s", $weather['current']['wind']['speed'], $weather['current']['wind']['dir'])
    : 'Still';

$this->setVar(array(
    'LOCATION'  => $weather['location']['city'] . ', ' . $weather['location']['country'],
    'TEMP'      => $weather['current']['temp']['actual'],
    'TEMP_FL'   => sprintf("%d",$weather['current']['temp']['feels_like']),
    'WIND'      => $wind,
    'HUMIDITY'  => ($weather['current']['humidity'] < 0) ? '0%' : $weather['current']['humidity'],
    'PERIOD'    => $forecastDate->getLong(),
    'C_IMAGE'   => $imageLocation . parse_url($weather['current']['styles']['wxug_icon'], PHP_URL_PATH),
    'C_PRECIS'  => $weather['current']['conditions']
));

$count = min(count($weather['forecast']), 6);

if($count) {
    $width = (int)(100 / $count);

    for($i = 0; $i < $count; $i++) {

        $forecastConditions = $weather['forecast'][$i];
        $forecastDate = new Alaress_Date_Format($forecastConditions['datetime'], 'U');
        $this->setVar(array(
			"FC_CURRENT"=> ($i == 0) ? 'active' : '',
            "FC_DAY"    => $forecastDate->getUsingFormat('D'),
            "FC_IMAGE"  => $imageLocation . parse_url($forecastConditions['styles']['wxug_icon'], PHP_URL_PATH),
            "FC_PRECIS" => $forecastConditions['conditions'],
            "FC_HIGH"   => $forecastConditions['temp']['high'],
            "FC_LOW"    => $forecastConditions['temp']['low'],
            'FC_WIDTH'  => $width
        ));

        $this->parse('forecast_day_block', 'forecast_day', true);
    }
}

//dont display blocks if they dont have any data
if($weather['location']['city']) {
    $this->parse("current_block", "current");
    $this->parse("forecast_block", "forecast");
} else {
    $this->parse("weather_unavailable_block", "weather_unavailable");
}

//ouput drop down box of available locations
$locations = array(
    array('LOCATION_VALUE' => 'Australia/Adelaide', 'LOCATION_NAME' => 'Adelaide', 'ON_OFF' => ''),
    array('LOCATION_VALUE' => 'Australia/Brisbane', 'LOCATION_NAME' => 'Brisbane', 'ON_OFF' => 'dark'),
    array('LOCATION_VALUE' => 'Australia/Canberra', 'LOCATION_NAME' => 'Canberra', 'ON_OFF' => ''),
    array('LOCATION_VALUE' => 'Australia/Darwin', 'LOCATION_NAME' => 'Darwin', 'ON_OFF' => 'dark'),
    array('LOCATION_VALUE' => 'Australia/Hobart', 'LOCATION_NAME' => 'Hobart', 'ON_OFF' => ''),
    array('LOCATION_VALUE' => 'Australia/Melbourne', 'LOCATION_NAME' => 'Melbourne', 'ON_OFF' => 'dark'),
    array('LOCATION_VALUE' => 'Australia/Perth', 'LOCATION_NAME' => 'Perth', 'ON_OFF' => ''),
    array('LOCATION_VALUE' => 'Australia/Sydney', 'LOCATION_NAME' => 'Sydney', 'ON_OFF' => 'dark')
);

$this->dropDown('regions', $locations, $location);

$url = new Url($weather['attribution']['url']);
$this->setVar(array(
    'SUPPLIED_BY_NAME' => $weather['attribution']['name'],
    'SUPPLIED_BY_URL'  => $url->getBase64EncodedRedirect(),
    'SUPPLIED_BY_LOGO' => 'http://icons.wxug.com/logos/PNG/wundergroundLogo_4c_horz.png',
));
