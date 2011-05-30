<?php
//dont output weather if not enabled or you could just say output weather if enabled
if(Cfg::get('weather_enable'))
{
	require_once('../library/xml.php');
	$weather = new XML('http://www.google.com/ig/api?weather=' . urlencode(Cfg::get('weather_location')));
	
	$imageLocation = '/weather/image.php?path=';

	for($i = 0; $i < 3; $i++) {
		$forecast_conditions = $weather->weather->forecast_conditions[$i];
        $url = parse_url((string)$forecast_conditions->icon['data']);
		$this->setVar(array(
			"DAY$i"     => $forecast_conditions->day_of_week['data'],
			"IMAGE$i"   => $imageLocation.urlencode($url['path']),
			"PRECIS$i"  => $forecast_conditions->condition['data'],
			"HIGH$i"    => round(($forecast_conditions->high['data'] - 32) / 1.8),
			"LOW$i"     => round(($forecast_conditions->low['data'] - 32) / 1.8)
		));
	}


	$this->setvar(array(
            'WEATHER_ICON'  => $imageLocation.$weather->weather->current_conditions->icon['data'],
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
