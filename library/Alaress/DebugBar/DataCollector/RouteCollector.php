<?php
/**
 * Collector for the current Aura route.
 *
 * This is designed to inform the user about which Aura controller method is
 * being called in order to render the page.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\DebugBar\DataCollector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

class RouteCollector extends DataCollector implements Renderable
{
    /**
     * @var string
     */
    private $route;

    /**
     * Constructor
     *
     * @param string $route
     */
    public function __construct($route)
    {
        $this->route = $route;
    }

    /**
     * {@inheritDoc}
     */
    public function collect()
    {
        return ['route' => $this->route];
    }

    /**
     * Sets the route to display.
     *
     * @param string $route
     * @return $this
     */
    public function setRoute($route)
    {
        $this->route = $route;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'route';
    }

    /**
     * {@inheritDoc}
     */
    public function getWidgets()
    {
        return [
            "route" => [
                "icon"    => "sun-o",
                "map"     => "route.route",
                "default" => "''",
            ],
        ];
    }
}
