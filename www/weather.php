<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//weather display object
require_once('../library/xml.php');

//create weather
$location = empty($this->arg[0]) ? WEATHER_location : $this->arg[0];
$weather = new XML('http://www.google.com/ig/api?weather=' . urlencode($location));

$this->setFile(TEMPLATE_content, 'weather/weather.html');
$this->setTitle('Weather Forecast');

$this->setBlock(TEMPLATE_content, "current", "current_block");
$this->setBlock(TEMPLATE_content, "forecast", "forecast_block");

//set current weather
$this->setVar(array(
        'LOCATION'  => $weather->weather->forecast_information->city['data'],
        'TEMP'      => $weather->weather->current_conditions->temp_c['data'],
        'WIND'      => substr($weather->weather->current_conditions->wind_condition['data'], 6),
        'HUMIDITY'  => substr($weather->weather->current_conditions->humidity['data'], 10),
        'PERIOD'    => date("F j, Y, g:i a", strtotime($weather->weather->forecast_information->current_date_time['data'])),
));

for($i = 0; $i < 4; $i++) {
    $forecast_conditions = $weather->weather->forecast_conditions[$i];
    $this->setVar(array(
        "DAY$i"     => $forecast_conditions->day_of_week['data'],
        "IMAGE$i"   => $forecast_conditions->icon['data'],
        "PRECIS$i"  => $forecast_conditions->condition['data'],
        "HIGH$i"    => round(($forecast_conditions->high['data'] - 32) / 1.8),
        "LOW$i"     => round(($forecast_conditions->low['data'] - 32) / 1.8)
    ));
}

//dont display blocks if they dont have any data
if($weather->weather != null) {
    $this->parse("current_block", "current");
    $this->parse("forecast_block", "forecast");
} else {
    $this->setVar("current_block", "");
    $this->setVar("forecast_block", "");
}

//ouput drop down box of available locations
$locations = array(
    array('LOCATION_VALUE' => 'Adelaide, South Australia', 'LOCATION_NAME' => 'Adelaide'),
    array('LOCATION_VALUE' => 'Brisbane, Queensland', 'LOCATION_NAME' => 'Brisbane'),
    array('LOCATION_VALUE' => 'Canberra, ACT', 'LOCATION_NAME' => 'Canberra'),
    array('LOCATION_VALUE' => 'Darwin, Northern Territory', 'LOCATION_NAME' => 'Darwin'),
    array('LOCATION_VALUE' => 'Hobart, Tasmania', 'LOCATION_NAME' => 'Hobart'),
    array('LOCATION_VALUE' => 'Melbourne, Victoria', 'LOCATION_NAME' => 'Melbourne'),
    array('LOCATION_VALUE' => 'Perth, Western Australia', 'LOCATION_NAME' => 'Perth'),
    array('LOCATION_VALUE' => 'Sydney, New South Wales', 'LOCATION_NAME' => 'Sydney')
);
$this->dropDown('regions', $locations, $location);

?>
