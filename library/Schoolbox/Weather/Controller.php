<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Weather;

use GuzzleHttp;
use Alaress\Web\Controller\Base;


class Controller extends Base
{
    // TODO these defaults are actually stored in the DB; should we be looking at the instead?
    // select * from schoolbox.component_config_definition where component_definition_id=5
    // see default_value field (only forecast limit is set but could easily update the other one)
    private const DEFAULT_LOCATION = 'Melbourne, Australia';
    private const DEFAULT_FORECAST_LIMIT = 3;

    private const AUS_CITIES = [
        'Melbourne',
        'Sydney',
        'Canberra',
        'Perth',
        'Adelaide',
        'Darwin',
        'Hobart',
        'Brisbane'
    ];

    /**
     * @api GET /weather
     */
    public function getWeatherDetails()
    {
        $configuredForecastLimit = $this->getConfigLoader()->get('size');
        // Special case 0 - this is the 'all' case -_- but our API has no such thing as an 'all' option, so we have to fall back to default
        $forecastLimit = (null === $configuredForecastLimit || 0 === (int)$configuredForecastLimit)
            ? self::DEFAULT_FORECAST_LIMIT
            : (int)$configuredForecastLimit;
        // 'days' param includes today, so we have to increment this by 1 to mimic previous API behavior
        $forecastLimit += 1;

        // Check if we're specifying the location in url params first
        $location = $_GET['location'];
        if (null === $location) {
            $configuredLocation = $this->getConfigLoader()->get('url');
            $location = (null === $configuredLocation || '' === $configuredLocation)
                ? self::DEFAULT_LOCATION
                : $this->getConfig('url');
        }
        $apixuClient = new ApixuClient();
        $twigData = $apixuClient->getForecast($forecastLimit, $location);
        $twigData['regions'] = self::AUS_CITIES;
        $this->getResponse()->content->set($this->buildHtmlOutput('@weather/weather.twig', $twigData));
    }
}
