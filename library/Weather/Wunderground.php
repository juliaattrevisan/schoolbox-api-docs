<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Weather_Wunderground implements Weather_Interface {

    /**
     * default location
     * @var string
     */
    private $location = 'Australia/Melbourne';

    /**
     * hardcoded default API key
     * @var string
     */
    private $APIKey = '784036067287ef7a';

    private $querystring = '';


    const FORECAST_MIN = 1;
    const FORECAST_MAX = 10;

    const HOURLY_MIN = 1;
    const HOURLY_MAX = 240; // 10 days of hourly forecasts: crazy.


    /**
     * @param array $features
     * @return bool|string
     */
    protected function getWundergroundData(array $features) {
        if(!($this->location && $this->APIKey)) {
            return false;
        }

        $validFeatures = array(
            'alerts','almanac','astronomy','conditions','currenthurricane','forecast','forecast10day','geolookup',
            'history','hourly','hourly10day','planner','rawtide','tide','webcams','yesterday'
        );

        for($i = 0; $i < count($features); ++$i) {
            if(!in_array($features[$i], $validFeatures)) {
                unset($features[$i]);
            }
        }

        $featureString = strtolower(implode('/', $features));

        $key = Cfg::get('weather_api_key');
        if(!$key) {
            $key = $this->APIKey;
        }

        $call = sprintf("http://api.wunderground.com/api/%s/%s/q/%s.json%s", $key, $featureString, $this->location, $this->querystring);

	    $cache = new Weather_Cache();
	    $cache->init($call);

	    return $cache->getStreamContents();
    }

    /**
     * @param $json
     * @return array
     */
    protected function parseJSON($json) {
        $data = json_decode($json);

        $ob = $data->current_observation;

        $current = array(
            'datetime'      => $ob->observation_epoch,
            'temp'          => array(
                'actual'            => $ob->temp_c,
                'feels_like'        => $ob->feelslike_c
            ),
            'conditions'    => $ob->weather,
            'styles'        => array(
                'wxug_icon'         => $ob->icon_url,
                'foreground'        => $ob->icon
            ),
            'rain'          => array(
                'next_hour'         => $ob->precip_1hr_metric,
                'today'             => $ob->precip_today_metric
            ),
            'wind'          => array(
                'speed'             => $ob->wind_kph,
                'gust'              => $ob->wind_gust_kph,
                'dir'               => $ob->wind_dir
            ),
            'humidity'      => $ob->relative_humidity,
            'link'          => $ob->ob_url
        );

        $forecast = array();

        if(count($data->forecast->simpleforecast->forecastday)) {
            foreach($data->forecast->simpleforecast->forecastday as $day) {
                $forecast[] = array(
                    'datetime'   => $day->date->epoch,
                    'temp'       => array(
                        'high'          => $day->high->celsius,
                        'low'           => $day->low->celsius,
                    ),
                    'conditions' => $day->conditions,
                    'styles'     => array(
                        'wxug_icon'     => $day->icon_url,
                        'foreground'    => $day->icon,
                        'background'    => $day->skyicon
                    ),
                    'rain'       => array(
                        'day'           => $day->qpf_day->mm,
                        'night'         => $day->qpf_night->mm,
                        'all'           => $day->qpf_allday->mm
                    ),
                    'wind'       => array(
                        'max'           => array(
                            'speed'             => $day->maxwind->kph,
                            'dir'               => $day->maxwind->dir
                        ),
                        'avg'           => array(
                            'speed'             => $day->avewind->kph,
                            'dir'               => $day->avewind->dir
                        ),
                    ),
                    'humidity'   => array(
                        'min'           => $day->minhumidity,
                        'max'           => $day->maxhumidity,
                        'avg'           => $day->avehumidity
                    )
                );
            }
        }


        return array(
            'location'    => array(
                'country'       => $data->location->country_name,
                'city'          => $data->location->city
            ),
            'current'     => $current,
            'forecast'    => $forecast,
            'attribution' => array(
                'name'          => $data->current_observation->image->title,
                'url'           => $data->current_observation->image->link,
                'logo'          => $data->current_observation->image->url
            ),
        );
    }

    public function guessLocation($locationString) {

        // echo "Taking a guess for '$locationString'... \n";

        // take a stab as to what format this is in, and render accordingly
        $strats = array(
            'ip' => "[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}",
            'geo' => "(\-?[0-9]+\.[0-9]+)[, /]*(\-?[0-9]+\.[0-9]+)",
            'airport' => "([A-Z]+){3,4}",
            'city' => "([A-Za-z \.-]+)/([A-Za-z \.-]+)"
        );

        foreach($strats as $method => $regex) {
            if(preg_match_all("#$regex#s", $locationString, $matches)) {
                // echo "looks like $method: ";
                $method = 'render_'.$method;
                $parsed = $this->$method($matches);
                // print_r($parsed);
                $this->setLocation($parsed);
                return true;
            }
        }
        // echo "got nothing... <br/>\n";
        return false;
    }


    /**
     * @param array $matches
     * @return array
     */
    private function render_ip($matches) {
        return array(
            'ip' => $matches[0][0]
        );
    }

    /**
     * @param array $matches
     * @return array
     */
    private function render_geo($matches) {
        return array(
            'lat' => $matches[1][0],
            'long' => $matches[2][0]
        );
    }

    /**
     * @param array $matches
     * @return array
     */
    private function render_airport($matches) {
        return array(
            'airport' => $matches[0][0]
        );
    }

    /**
     * @param array $matches
     * @return array
     */
    private function render_city($matches) {
        return array(
            'country' => str_replace(' ', '_', $matches[1][0]),
            'city' => str_replace(' ', '_', $matches[2][0])
        );

    }


    /* INTERFACE METHODS */

    /**
     * @param array $location
     * @return mixed
     */
    public function setLocation(array $location) {
        if(isset($location['lat']) && isset($location['long'])) {
            $this->location = sprintf('%0.1d,%0.1d', $location['lat'], $location['long']);
        }
        elseif(isset($location['country']) && isset($location['city'])) {
            $this->location = sprintf('%s/%s', str_replace(' ', '_', $location['country']), str_replace(' ', '_', $location['city']));
        }
        elseif(isset($location['airport'])) {
            $this->location = strtoupper($location['airport']);
        }
        elseif(isset($location['ip'])) {
            $this->location = 'autoip';
            $this->querystring = '?geo_ip=' . $location['ip'];
        }
        else {
            return false;
        }
        return true;
    }


    /**
     * @return array
     */
    public function getForecast() {
	        $json = $this->getWundergroundData(array('geolookup', 'forecast10day', 'conditions'));

	        if($json){
	            return $this->parseJSON($json);
	        }else{
		        return false;
	        }
    }

}
