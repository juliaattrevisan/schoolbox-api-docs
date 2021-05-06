<?php

/**
 * @copyright   Copyright 2021, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Geosnapshot\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;

/**
 * Class Controller
 *
 * Display an iframe of Geosnapshot galleries that matched a search string
 * specified in the component's configuration
 *
 * @package Schoolbox\Geosnapshot\Component\Dashboard
 */
class Controller extends Component_Dashboard_ControllerAbstract
{

    /**
     * Only render this component if Geosnapshot has been set up
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
        return $this->renderTwigTemplate($templateHandle, '@geosnapshotDashboard/component.twig', [], false, false, true);
    }
}
