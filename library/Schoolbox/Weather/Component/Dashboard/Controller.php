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
    // TODO these defaults are actually stored in the DB; should we be looking at the instead?
    // select * from schoolbox.component_config_definition where component_definition_id=5
    // see default_value field (only forecast limit is set but could easily update the other one)
    private const DEFAULT_LOCATION = 'Melbourne, Australia';
    private const DEFAULT_FORECAST_LIMIT = 3;

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
        $location = (null === $this->getConfig('url') || '' === $this->getConfig('url')) ? self::DEFAULT_LOCATION : $this->getConfig('url');
        $this->setComponentName('Weather in ' . $location);
    }


    /**
     * Get the data to put into the template
     * // TODO not sure what to use this for
     *
     * @return bool
     */
    public function execute(): bool
    {
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
        $configuredForecastLimit = $this->getConfig('size');

        // Special case 0 - this is the 'all' case -_- but our API has no such thing as an 'all' option, so we have to fall back to default
        $forecastLimit = (int)$configuredForecastLimit ?: self::DEFAULT_FORECAST_LIMIT;

        // 'days' param includes today, so we have to increment this by 1 to mimic previous API behavior
        $forecastLimit++;

        $location = trim($this->getConfig('url')) ?: self::DEFAULT_LOCATION;

        $apiKey = $this->getConfigMapper()->getConfigLoader()->get('weather_api_key');
        $apiUrl = $this->getConfigMapper()->getConfigLoader()->get('weather_api_url');
        $apixuClient = new ApixuClient($apiKey, $apiUrl);
        $data = $apixuClient->getForecast($forecastLimit, $location);
        return $this->renderTwigTemplate($templateHandle, '@weatherDashboard/component.twig', $data);
    }
}
