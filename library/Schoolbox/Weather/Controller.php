<?php

/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Weather;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * Always show a week's worth of data on the Weekly Forecast page
     */
    private const DEFAULT_FORECAST_LIMIT = 5;

    /**
     * @api GET /weather
     */
    public function getWeatherDetails()
    {
        /* Co-erce to string in case someone visits this page directly w/o a location querystring param
         */
        $location = (new Location())->detect((string)$this->request->query->get('location'), $this->getConfigLoader()->get('weather_location'));

        $apiKey = $this->getConfigLoader()->get('weather_api_key');
        $apiUrl = $this->getConfigLoader()->get('weather_api_url');

        $weatherClient = new WeatherstackClient($apiKey, $apiUrl);

        $weatherPayload = $weatherClient->getForecast(self::DEFAULT_FORECAST_LIMIT, $location);

        /* Overwrite the location for twig
         *
         * If I give MEL to apixu, it derives to [weatherLocation] => Melbourne Airport, Australia
         * If I give Melbourne Airport, Australia to apixu, it derives to [weatherLocation] => Melbourne, Australia
         */
        $this->getResponse()->content->set($this->buildHtmlOutput('@weather/weather.twig', $weatherPayload));
    }
}
