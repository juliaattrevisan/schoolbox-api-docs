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
     * hardcoded API key
     * @var string
     */
    private $APIKey = '784036067287ef7a';

    /**
     * where we will find weather data
     * @var string
     */
    private $cachePattern;

    const FORECAST_MIN = 1;
    const FORECAST_MAX = 10;

    const HOURLY_MIN = 1;
    const HOURLY_MAX = 240; // 10 days of hourly forecasts: crazy.


    public function __construct() {
        $this->cachePattern = SITE_root . '/data/cache/weather/wunderground.%s.cache';
    }

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

        $call = sprintf("http://api.wunderground.com/api/%s/%s/q/%s.json", $this->APIKey, $featureString, $this->location);

        $data = file_get_contents($call);

        if($data) {
            return $data;
        }

        return false;

    }

    /**
     * @param $json
     * @return array
     */
    protected function parseJSON($json) {
        $data = json_decode($json);

        // print_r($data);

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
        for($i = 0; $i < count($data->forecast->simpleforecast->forecastday); ++$i) {
            $day = $data->forecast->simpleforecast->forecastday[$i];
            $forecast[$i] = array(
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


    protected function getDefangedLocation() {
        return strtolower(preg_replace("#[^a-z0-9\-.]#si",'_',$this->location));
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
            $this->location = $location['ip'];
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

        $file = sprintf($this->cachePattern, $this->getDefangedLocation());

        // update every half hour: suppress error
        if(!(file_exists($file) && time() - @filemtime($file) < 1800)) {

            if(($json = $this->getWundergroundData(array('geolookup', 'forecast10day', 'conditions'))) === false) {
                return array();
            }

            $fp = fopen($file, 'w');
            fwrite($fp, $json);
            fclose($fp);

            return $this->parseJSON($json);
        }

        // echo "retrieved from cache! ";
        return $this->parseJSON(file_get_contents($file));
    }

}
