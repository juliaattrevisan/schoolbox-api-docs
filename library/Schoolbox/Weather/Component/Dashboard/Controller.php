<?php
/**
 * Controller
 *
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Weather\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use Schoolbox\User;
use Schoolbox\Weather\ApixuClient;
use SitePage;

class Controller extends Component_Dashboard_ControllerAbstract
{
    // TODO these global defaults should come from the database. This will be addressed in SA-7986
    private const DEFAULT_LOCATION = 'Melbourne, Australia';
    private const DEFAULT_FORECAST_LIMIT = 3;

    /**
     * @var array $weatherPayload
     */
    private $weatherPayload;


    /**
     * Controller constructor.
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     * @throws \InvalidArgumentException
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage)
    {
        parent::__construct($instance, $user, $sitepage);
    }


    /**
     * Get the data to put into the template
     *
     * @return bool
     */
    public function execute(): bool
    {
        /* Location for which to get the weather
         * First check the component configuration - if there's a location there, use that
         * Then check the system config for a default location - if there's a location there, use that
         * Otherwise if that doesn't exist for some reason, default to Melbourne Australia
         */
        $location = trim($this->getConfig('url')) ?: self::DEFAULT_LOCATION;

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
