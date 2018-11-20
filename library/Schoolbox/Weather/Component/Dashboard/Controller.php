<?php
/**
 * Controller
 *
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Weather\Component\Dashboard;

use Component_Config_Size;
use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\News\Topic;
use Schoolbox\News\TopicMapper;
use Schoolbox\User;
use SitePage;

class Controller extends Component_Dashboard_ControllerAbstract
{

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
        // Return true if API call success; otherwise return false?
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
        $data = $this->getForecast();
        return $this->renderTwigTemplate($templateHandle, '@weatherDashboard/component.twig', $data);
    }


    /**
     * Get a JSON representation of the data from the API
     *
     * @return array
     */
    private function getForecast() : array
    {
        $data = [
            'weatherUnavailable' => true
        ];

        return $data;
    }
}
