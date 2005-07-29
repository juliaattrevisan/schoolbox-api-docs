<?php

//class to handle getting weather and caching and formatting and outputting weather
class Weather
{	
	//location for weather cache file
	var $cache_file = WEATHER_CURRENT_CACHE_LOCATION;
	//time cache file lives for
	var $cache_time = WEATHER_CACHE;
	
	function Weather($type = 0, $location = 0)
	{
		//Can be told to only parse current or to parse both	
		//check to see where we should get our weather from (cache/source)
		if( (file_exists($this->cache_file)) && ((filectime($this->cache_file)+$this->cache_time) - date("U") > 0) && 
		(filesize($this->cache_file)))		
		{
			//$this->getWeatherFromCache();
		}
		else
		{
			$this->getWeatherFromSource();
			//$this->cacheWeather();
		}	
		
		//if location not specified default 
		if($location == 0)
			$this->areacode = WEATHER_LOCATION;
		else
			$this->areacode = $location;

		if(!strcmp("Current", $type))
		{
			$this->setCurrentFromXML();
		}
		else
		{
			$this->setForecastFromXML();
			$this->setCurrentFromXML();

		}
	}

	function setCurrentFromXML()
	{
		$file = WEATHER_CURRENT_CACHE_LOCATION;
		$this->parseXML($file);
		
		//check we found at least what we were looking for
		if($this->location > 0)
		{
			//only 1 current forecast for location so accessed like this
			$this->Current = 1;
			$this->City = $this->data[0]["SCREEN_NAME"];
			$this->Date = $this->data[0]["DATE"];
			$this->Day = $this->data[0]["DAY_NAME"];
			$this->Temp = $this->data[0]["T_CURR"];
			$this->T_DP = $this->data[0]["T_DP"];
			$this->Humid = $this->data[0]["REL_H"];
			$this->WindS = $this->data[0]["SPEED_KPH"];
			$this->WindD = $this->data[0]["DIR_DEGREES"];
			$this->WindC = $this->data[0]["DIR_COMPASS"];
			$this->Period = $this->data[0]["PERIOD"];
			$this->Rain = $this->data[0]["AMOUNT_MM"];
		}
		else
			$this->Current = 0;
	}		
		
	function setForecastFromXML()
	{
		$file = WEATHER_FORECAST_CACHE_LOCATION;
		$this->parseXML($file);
	
		if($this->location > 0)
		{	
			$this->Forecast = 1;
			//forecast has 4 entries so we will step through each and put them in an array
			for($i=0; $i<4; $i++)
			{
				$this->forecast[$i]["City"] = $this->data[$i]["SCREEN_NAME"];
				$this->forecast[$i]["Date"] = $this->data[$i]["DATE"];
				$this->forecast[$i]["DayNum"] = $this->data[$i]["DAY_NUM"];
				$this->forecast[$i]["Day"] = $this->data[$i]["DAY_NAME"];
				$this->forecast[$i]["Precis"] = $this->data[$i]["PRECIS"];
				$this->forecast[$i]["Icon"] = str_replace(" ", "_", strtolower($this->data[$i]["ICON"])).".gif";
				$this->forecast[$i]["Min"] = $this->data[$i]["T_MIN"];
				$this->forecast[$i]["Max"] = $this->data[$i]["T_MAX"];
			}
		}
		else
			$this->Forecast = 0;
	}

	function getWeatherFromSource()
	{
		$ftp_server = WEATHER_SERVER;
		$ftp_user_name = WEATHER_USERNAME;
		$ftp_user_pass = WEATHER_PASSWORD;

		// set up basic connection
		$conn_id = @ftp_connect($ftp_server, 21, 10); 
		if($conn_id)
		{
			// login with username and password
			$login_result = @ftp_login($conn_id, $ftp_user_name, $ftp_user_pass); 

			// check connection
			if ($login_result) 
			{
				$currentWeather = WEATHER_CURRENT_CACHE_LOCATION;
				$forecastWeather = WEATHER_FORECAST_CACHE_LOCATION;

				// enter active mode
				ftp_pasv( $conn_id, false);

				// download current weather file
				$download = ftp_get($conn_id, $currentWeather, WEATHER_CURRENT_FILE, FTP_ASCII); 
				$download = ftp_get($conn_id, $forecastWeather, WEATHER_FORECAST_FILE, FTP_ASCII); 

				ftp_close($conn_id);
			}
		}
	}

	function startElement($parser, $name, $attrs) 
	{
		$this->currentTag = $name;
	}

	function endElement($parser, $name) 
	{
		//if end of block
		if($name == "OBS" || $name == "FORECAST")
		{
			//create location array
			$this->locationArray[$this->data[$this->location]["SCREEN_NAME"]] = $this->data[$this->location]["SITE_NUM"];

			//check if this block contains relevant info
			if($this->data[$this->location]["SITE_NUM"] == $this->areacode)
			{
				//if it does increment block counter otherwise just overwrite it
				$this->location++;
			}
		}						

	}

	function characterData($parser, $data)
	{
		if(preg_match('/[A-Za-z0-9]+/', $data))
		{
			$this->data[$this->location][$this->currentTag] = $data;	
		}
	}

	function parseXML($file)
	{

		$this->location = 0;

		$xml_parser = xml_parser_create();
		xml_set_object($xml_parser, &$this);
		xml_set_element_handler($xml_parser, "startElement", "endElement");
		xml_set_character_data_handler($xml_parser, "characterData");	
		
		if (!($fp = fopen($file, "r"))) 
		{
			die("could not open XML input");
		}

		while ($data = fread($fp, 4096)) 
		{
    			if (!xml_parse($xml_parser, $data, feof($fp))) 
			{
				die(sprintf("XML error: %s at line %d",
				xml_error_string(xml_get_error_code($xml_parser)),
				xml_get_current_line_number($xml_parser)));
    			}
		}

		xml_parser_free($xml_parser);

	}

}
?>
