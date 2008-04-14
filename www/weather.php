<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//weather display object
require_once("weather/weather.php");

//create weather
$weather = new Weather("All", WEATHER_LOCATION);

$this->template->set_file(TEMPLATE_content, $this->getTemplate('weather/weather.html'));
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
$this->setVar("LOCDROP", html_select("code' onChange='this.form.submit();", $weather->locationArray, $weather->areacode));






/*



require_once("include/session.php");
require_once("include/include.php");


class WeatherFore 
{
	function WeatherFore()
	{
	}
  
        function displayForecast($code = 0)
        {
                //output week to template

                $this->t_body = new Template(TPL_directory);
                $this->t_body->set_file("tplBODY", "weather/weather.htm");

		include_once("include/page.php");
		
		//create weather
		$weather = new Weather("All", WEATHER_LOCATION);

		$this->setVar("TITLE", HEADING." - Weather Forecast");
		$this->setVar("LOCATION", $weather->City);
		
		$this->setBlock("tplBODY", "current", "current_block");
		$this->setBlock("tplBODY", "forecast", "forecast_block");

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
		$this->setVar("LOCDROP", html_select("code' onChange='this.form.submit();", 
				$weather->locationArray, $weather->areacode));
		

                $this->t_body->pparse("finaloutput", "tplBODY");

        }

}

$myPage = new WeatherFore;
$myPage->displayForecast($_REQUEST['code']);
*/
?>
