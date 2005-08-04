<?php

//class to handle getting weather and caching and formatting and outputting weather
class Weather
{	
	//location for weather cache file
	var $cache_file = WEATHER_CURRENT_LOCAL_CACHE_LOCATION;
	//time cache file lives for
	var $cache_time = WEATHER_CACHE;
	
	function Weather($type = 0, $location = 0)
	{
		//Can be told to only parse current or to parse both	
		//check to see where we should get our weather from (cache/source)
		if( !((file_exists($this->cache_file)) && ((filectime($this->cache_file)+$this->cache_time) - date("U") > 0) && 
		(filesize($this->cache_file))))		
		{
			$this->getWeatherFromSource();
		}
		
		//if location not specified default 
		if($location == 0)
			$this->areacode = WEATHER_LOCATION;
		else
			$this->areacode = $location;

		if(!strcmp("Current", $type))
		{
			$this->setLocalCurrentFromXML();
			$this->setForecastFromXML();
		}
		else
		{
			$this->setForecastFromXML();
			$this->setCurrentFromXML();

		}
	}

	function setCurrentFromXML()
	{
		$file = WEATHER_CURRENT_AUS_CACHE_LOCATION;
		$this->parseXML($file);
		
		//check we found at least what we were looking for
		if($this->data[$this->areacode]["NAME"])
		{
			//only 1 current forecast for location so accessed like this
			$this->Current = 1;
			$this->City = $this->data[$this->areacode]["NAME"];
			$this->Date = $this->data[$this->areacode]["OBS_TIME_LOCAL"];
			$this->Temp = $this->data[$this->areacode]["TEMP_C"];
			$this->T_DP = $this->data[$this->areacode]["DP_C"];
			$this->Humid = $this->data[$this->areacode]["RH"];
			$this->WindSpeed = $this->data[$this->areacode]["WIND_SPEED_KPH"];
			$this->WindDirection = $this->data[$this->areacode]["WIND_DIR_DEGREES"];
			$this->WindCompass = $this->data[$this->areacode]["WIND_DIR_COMPASS"];
			$this->WindChill = $this->data[$this->areacode]["WIND_CHILL_C"];
			$this->Rain = $this->data[$this->areacode]["RAINFALL_MM"];
		}
		else
			$this->Current = 0;
	}		
	
	function setLocalCurrentFromXML()
	{
	
		$file = WEATHER_CURRENT_LOCAL_CACHE_LOCATION;
		$this->parseXML($file);
		
		//check we found at least what we were looking for
		if($this->data[$this->areacode]["NAME"])
		{
			//only 1 current forecast for location so accessed like this
			$this->Current = 1;
			$this->City = $this->data[$this->areacode]["NAME"];
			$this->Date = $this->data[$this->areacode]["OBS_TIME_LOCAL"];
			$this->Temp = $this->data[$this->areacode]["TEMP_C"];
			$this->T_DP = $this->data[$this->areacode]["DP_C"];
			$this->Humid = $this->data[$this->areacode]["RH"];
			$this->WindSpeed = $this->data[$this->areacode]["WIND_SPEED_KPH"];
			$this->WindDirection = $this->data[$this->areacode]["WIND_DIR_DEGREES"];
			$this->WindCompass = $this->data[$this->areacode]["WIND_DIR_COMPASS"];
			$this->WindChill = $this->data[$this->areacode]["WIND_CHILL_C"];
			$this->Rain = $this->data[$this->areacode]["RAINFALL_MM"];
		}
		else
			$this->Current = 0;
	}	

		
	function setForecastFromXML()
	{
		$file = WEATHER_FORECAST_CACHE_LOCATION;
		$this->parseXML($file);
	
		if($this->data[$this->areacode]["NAME"])
		{	
			$this->Forecast = 1;
			//forecast has 4 entries so we will step through each and put them in an array
			for($i=0; $i<4; $i++)
			{
				$this->forecast[$i]["City"] = $this->data[$this->areacode]["NAME"];
				$this->forecast[$i]["Date"] = $this->data[$this->areacode]['forecast']["DATE"][$i];
				//$this->forecast[$i]["DayNum"] = $this->data[$this->areacode]["DAY_NUM"];
				$this->forecast[$i]["Day"] = $this->data[$this->areacode]['forecast']["DAY_NAME"][$i];
				$this->forecast[$i]["Precis"] = $this->data[$this->areacode]['forecast']["ICON"][$i];
				$this->forecast[$i]["Icon"] = str_replace(" ", "_", strtolower($this->data[$this->areacode]['forecast']["ICON"][$i])).".gif";
				$this->forecast[$i]["Min"] = $this->data[$this->areacode]['forecast']["TEMP_MIN_C"][$i];
				$this->forecast[$i]["Max"] = $this->data[$this->areacode]['forecast']["TEMP_MAX_C"][$i];
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
				$currentLocalWeather = WEATHER_CURRENT_LOCAL_CACHE_LOCATION;
				$currentAusWeather = WEATHER_CURRENT_AUS_CACHE_LOCATION;
				$forecastWeather = WEATHER_FORECAST_CACHE_LOCATION;

				// enter active mode
				ftp_pasv( $conn_id, false);

				// download current weather file
				$download = ftp_get($conn_id, $currentLocalWeather, WEATHER_CURRENT_LOCAL_FILE, FTP_ASCII); 
				$download = ftp_get($conn_id, $currentAusWeather, WEATHER_CURRENT_AUS_FILE, FTP_ASCII); 
				$download = ftp_get($conn_id, $forecastWeather, WEATHER_FORECAST_FILE, FTP_ASCII); 

				ftp_close($conn_id);
			}
		}
	}

	function startElement($parser, $name, $attrs) 
	{
		if($name == "LOCATION")
		{
			$this->location = $attrs['CODE'];
			$this->data[$this->location]['NAME'] = $attrs['NAME'];
			$this->data[$this->location]['STATE'] = $attrs['STATE'];
			
			$this->locationArray[$attrs['NAME']] = $attrs['CODE'];
		}
		
		if($name == "FORECAST")
			$this->forecasttag = 1;
			
		$this->currentTag = $name;
		//$this->currentTag['attributes'] = $attrs;
	}

	function endElement($parser, $name) 
	{
		if($name == "FORECAST")
			$this->forecasttag = 0;
	
		/*
		//if end of block
		if($name == "OBS" || $name == "FORECAST" || $name == "LOCATION")
		{
			//create location array
			$this->locationArray[$this->data[$this->location]["SCREEN_NAME"]] = $this->data[$this->location]["SITE_NUM"];
			
			//check if this block contains relevant info
			if($this->data[$this->location]["SITE_NUM"] == $this->areacode)
			{
				//if it does increment block counter otherwise just overwrite it
				
			}
			$this->location++;
		}						
		*/
	}

	function characterData($parser, $data)
	{
		if(preg_match('/[A-Za-z0-9\.\-]+/', $data))
		{	
			if($this->forecasttag)
				$this->data[$this->location]['forecast'][$this->currentTag][] = $data;
			else
				$this->data[$this->location][$this->currentTag] = $data;	
		}
		
 		//$this->data[$this->location][$this->currentTag['name']][] = $data;
		
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
		
		//print_r($this->data);

	}

}
?>
