<?php
/**
 * Rendering external events
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Calendar\Event\Type\External;

use Calendar\Event\Type\EventBase;
use Calendar\Event\Type\EventInterface;

class Event extends EventBase implements EventInterface {

    /**
     * Renderer classes
     */
    protected $renderClasses = array(   'javascript'    => Renderer\Javascript::class,
                                        'html'          => Renderer\Html::class,
                                        'vcal'          => Renderer\VObject::class,
                                        'csv'           => Renderer\Csv::class,
    );

    /**
     * Event location
     *
     * @var string
     */
    protected $location;

    /**
     * Set location
     *
     * @param $location
     */
    public function setLocation($location)
    {
        $this->location = $location;
    }

    /**
     * Get location
     *
     * @return null
     */
    public function getLocation()
    {
        return $this->location;
    }

}
