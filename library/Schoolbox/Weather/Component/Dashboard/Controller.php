<?php
/**
 * Controller
 *
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Weather\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use Schoolbox\Weather\ApixuClient;

class Controller extends Component_Dashboard_ControllerAbstract
{
    /**
     * Default location for which to obtain weather data
     */
    private const DEFAULT_LOCATION = 'Melbourne, Australia';

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
        $location = $this->determineWeatherLocation();

        $this->setComponentName("Weather in {$location}");

        // Number of days of forecast to show.
        // Special case 0 - this is the 'all' case -_- but our API has no such thing as an 'all' option,
        // so we have to fall back to default
        $forecastLimit = (int)$this->getConfig('size') ?: self::DEFAULT_FORECAST_LIMIT;

        // 'days' param includes today, so we have to increment this by 1 to mimic previous API behavior
        $forecastLimit++;

        $apiKey = $this->getConfigMapper()->getConfigLoader()->get('weather_api_key');
        $apiUrl = $this->getConfigMapper()->getConfigLoader()->get('weather_api_url');

        $this->weatherPayload = (new ApixuClient($apiKey, $apiUrl))->getForecast($forecastLimit, $location);

        return true;
    }


    /**
     * Location for which to get the weather
     *
     * @return string
     */
    private function determineWeatherLocation(): string
    {
        /* First check the component configuration - if there's a location there, use that
         */
        $location = trim($this->getConfig('url'));

        if ('' !== $location) {
            return $location;
        }

        /* Then check the system config for a default location - if there's a location there, use that
         */
        $location = trim($this->getConfigMapper()->getConfigLoader()->get('weather_location'));

        if ('' !== $location) {
            return $location;
        }

        /* Otherwise if that doesn't exist for some reason, default to Melbourne Australia
         */
        return self::DEFAULT_LOCATION;
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
