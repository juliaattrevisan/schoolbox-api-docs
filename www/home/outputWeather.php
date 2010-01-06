<?php
//dont output weather if not enabled
if(WEATHER_enable)
{
	require_once('../library/xml.php');
	$weather = new XML('http://www.google.com/ig/api?weather=' . urlencode(WEATHER_location));
	$this->setvar(array(
            'WEATHER_ICON'  => $weather->weather->current_conditions->icon['data'],
            'TEMP'          => $weather->weather->current_conditions->temp_c['data'],
            'WIND'          => substr($weather->weather->current_conditions->wind_condition['data'], 6),
            'HUMIDITY'      => substr($weather->weather->current_conditions->humidity['data'], 10),
            'ISSUED'        => date("g:i a", strtotime($weather->weather->forecast_information->current_date_time['data']))
        ));

	$this->parse('POS'.$component['position'], $component['name']);
}
else 
	$this->setVar('POS'.$component['position'], '');
?>
