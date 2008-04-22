<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//weather display object
require_once("weather/weather.php");

//create weather
$weather = new Weather("All", $this->arg[0]);

$this->setFile(TEMPLATE_content, 'weather/weather.html');
$this->setVar("TITLE", HEADING." - Weather Forecast");
$this->setVar("LOCATION", $weather->City);

$this->setBlock(TEMPLATE_content, "current", "current_block");
$this->setBlock(TEMPLATE_content, "forecast", "forecast_block");

//set current weather
$this->setVar("TEMP", $weather->Temp);
$this->setVar("DIRECTION", $weather->WindCompass." (".$weather->WindDirection."&deg)");
$this->setVar("SPEED", $weather->WindSpeed);
$this->setVar("HUMIDITY", $weather->Humid);
$this->setVar("RAIN", $weather->Rain);
$this->setVar("PERIOD", date("F j, Y, g:i a", strtotime($weather->Date)));
	
for($i = 0; $i < 4; $i++)
{
	//set days
	$this->setVar("DAY".($i+1), $weather->forecast[$i]["Day"]);

	//set image
	$this->setVar("IMAGE".($i+1), $weather->forecast[$i]["Icon"]);
	
	//set forecast
	$this->setVar("PRECIS".($i+1), $weather->forecast[$i]["Precis"]);

	//set high
	$this->setVar("HIGH".($i+1), $weather->forecast[$i]["Max"]);

	//set lows
	$this->setVar("LOW".($i+1), $weather->forecast[$i]["Min"]);
}

//dont display blocks if they dont have any data
if($weather->Current)
	$this->parse("current_block", "current");
else
{
	$this->setVar("LOCATION", $weather->forecast[0]["City"]);
	$this->setVar("current_block", "");
}
if($weather->Forecast)
	$this->parse("forecast_block", "forecast");
else
	$this->setVar("forecast_block", "");

//ouput drop down box of available locations
include("html-parse.php");
$this->setVar("LOCDROP", html_select("code' onchange='window.open(\"/weather/\"+this.value,\"_self\", \"\");", $weather->locationArray, $weather->areacode));

?>
