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
    private const DEFAULT_LOCATION = 'Melbourne, Australia';
    private const DEFAULT_FORECAST_LIMIT = 5;

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
        // Lol ew
        // Attempt to get dashboard component's location config by looking for the first weather component
        // (there can only ever be one)
        $dashboardComponentInstance = Component_InstanceMapper::getInstance()->findOneByColumn('component_definition_id', 5);
        $configuredLocation = Component_ConfigMapper::getInstance()->findValue('url', $dashboardComponentInstance->getId());

        $apixuClient = new ApixuClient();
        $twigData = $apixuClient->getForecast(self::DEFAULT_FORECAST_LIMIT, $_GET['location'] ?: ($configuredLocation ?: self::DEFAULT_LOCATION));
        $twigData['regions'] = self::AUS_CITIES;
        $this->getResponse()->content->set($this->buildHtmlOutput('@weather/weather.twig', $twigData));
    }
}
