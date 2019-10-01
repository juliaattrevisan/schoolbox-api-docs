<?php
/**
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Weather\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use Schoolbox\Weather\Location;
use Schoolbox\Weather\WeatherstackClient;

/**
 * Class Controller
 *
 * Display weather forecast data on the dashboard component
 *
 * @package Schoolbox\Weather\Component\Dashboard
 */
class Controller extends Component_Dashboard_ControllerAbstract
{

    /**
     * Default number of days for the Forecast view which is what you see
     * on the dashboard component itself
     */
    private const DEFAULT_FORECAST_LIMIT = 3;

    /**
     * @var array $weatherPayload
     */
    private $weatherPayload;


    /**
     * Get the data to put into the template
     *
     * @return bool
     */
    public function execute(): bool
    {
        $location = (new Location())->detect($this->getConfig('url'), $this->getConfigMapper()->getConfigLoader()->get('weather_location'));

        // Number of days of forecast to show.
        // Special case 0 - this is the 'all' case -_- but our API has no such thing as an 'all' option,
        // so we have to fall back to default
        $forecastLimit = (int)$this->getConfig('size') ?: self::DEFAULT_FORECAST_LIMIT;


        // 'days' param includes today, so we have to increment this by 1 to mimic previous API behavior
        $forecastLimit++;

        $apiKey = $this->getConfigMapper()->getConfigLoader()->get('weather_api_key');
        $apiUrl = $this->getConfigMapper()->getConfigLoader()->get('weather_api_url');

        $this->weatherPayload                           = (new WeatherstackClient($apiKey, $apiUrl))->getForecast($forecastLimit, $location);
        $this->weatherPayload['weeklyForecastLocation'] = $location;

        $this->setComponentName("Weather in {$this->weatherPayload['weatherLocation']}");

        return true;
    }


    /**
     * Render the component's template
     *
     * @param string $templateHandle
     * @return bool
     * @throws \Exception
     */
    public function renderTemplate($templateHandle): bool
    {
        return $this->renderTwigTemplate($templateHandle, '@weatherDashboard/component.twig', $this->weatherPayload);
    }
}
