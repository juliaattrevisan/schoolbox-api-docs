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
use Component_ConfigMapper;
use Component_DefinitionMapper;
use Component_InstanceMapper;

class Controller extends Base
{
    // TODO these global defaults should come from the database. This will be addressed in SA-7986
    private const DEFAULT_LOCATION = 'Melbourne, Australia';
    private const DEFAULT_FORECAST_LIMIT = 5;

    /**
     * @api GET /weather
     */
    public function getWeatherDetails()
    {
        $location = $_GET['location'] ?: self::DEFAULT_LOCATION;
        $apixuClient = new ApixuClient($this->getConfigLoader()->get('weather_api_key'), $this->getConfigLoader()->get('weather_api_url'));
        $twigData = $apixuClient->getForecast(self::DEFAULT_FORECAST_LIMIT, $location);
        $this->getResponse()->content->set($this->buildHtmlOutput('@weather/weather.twig', $twigData));
    }
}
