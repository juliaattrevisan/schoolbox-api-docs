<?php
/************************************************************
*                                                           *
*         Copyright (c) 2002-2003 Lektec Consulting         *
*                                                           *
************************************************************/
//weather display object

require_once("include/session.php");
require_once("include/include.php");

if(SITE_location == 'wenona')
	require_once("weather/wenona-weather.php");
else
	require_once("weather/weather.php");

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
		$weather = new Weather("All", $code);

		$this->t_body->set_var("TITLE", HEADING." - Weather Forecast");
		$this->t_body->set_var("LOCATION", $weather->City);
		
		$this->t_body->set_block("tplBODY", "current", "current_block");
		$this->t_body->set_block("tplBODY", "forecast", "forecast_block");

		//set current weather
		$this->t_body->set_var("TEMP", $weather->Temp);
		$this->t_body->set_var("DIRECTION", $weather->WindCompass." (".$weather->WindDirection."&deg)");
		$this->t_body->set_var("SPEED", $weather->WindSpeed);
		$this->t_body->set_var("HUMIDITY", $weather->Humid);
		$this->t_body->set_var("RAIN", $weather->Rain);
		$this->t_body->set_var("PERIOD", date("F j, Y, g:i a", strtotime($weather->Date)));
			
		for($i = 0; $i < 4; $i++)
		{
			//set days
			$this->t_body->set_var("DAY".($i+1), $weather->forecast[$i]["Day"]);

			//set image
			$this->t_body->set_var("IMAGE".($i+1), $weather->forecast[$i]["Icon"]);
			
			//set forecast
			$this->t_body->set_var("PRECIS".($i+1), $weather->forecast[$i]["Precis"]);
		
			//set high
			$this->t_body->set_var("HIGH".($i+1), $weather->forecast[$i]["Max"]);
	
			//set lows
			$this->t_body->set_var("LOW".($i+1), $weather->forecast[$i]["Min"]);
		}
		
		//dont display blocks if they dont have any data
		if($weather->Current)
			$this->t_body->parse("current_block", "current");
		else
		{
			$this->t_body->set_var("LOCATION", $weather->forecast[0]["City"]);
			$this->t_body->set_var("current_block", "");
		}
		if($weather->Forecast)
			$this->t_body->parse("forecast_block", "forecast");
		else
			$this->t_body->set_var("forecast_block", "");

		//ouput drop down box of available locations
		include("html-parse.php");
		$this->t_body->set_var("LOCDROP", html_select("code' onChange='this.form.submit();", 
				$weather->locationArray, $weather->areacode));
		

                $this->t_body->pparse("finaloutput", "tplBODY");

        }

}

$myPage = new WeatherFore;
$myPage->displayForecast($_REQUEST['code']);

?>
