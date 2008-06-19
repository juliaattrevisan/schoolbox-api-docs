<?php
//dont output weather if not enabled
if(WEATHER_enable)
{
	require_once '../library/weather/weather.php';
	$weather = new Weather("Current");
	
	$this->setVar("WEATHER_ICON", $weather->forecast[0]["Icon"]);
	$this->setVar("PRECIS", $weather->forecast[0]["Precis"]);
	$this->setVar("TEMP", $weather->Temp);
	$this->setVar("DIRECTION", $weather->WindCompass);
	$this->setVar("SPEED", $weather->WindSpeed);
	$this->setVar("HUMIDITY", $weather->Humid);
	$this->setVar("ISSUED", date("g:i a", strtotime(str_replace('T', ' ',$weather->Date))));
	
	$this->parse('POS'.$component['position'], $component['name']);
}
else 
	$this->setVar('POS'.$component['position'], '');
?>
