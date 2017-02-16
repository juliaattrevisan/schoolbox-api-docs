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
     * Set location
     *
     * @param $location
     */
    public function setLocation($location)
    {
        $this->detail = $location;
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

    /**
     * Set room
     *
     * @param $room
     */
    public function setRoom($room) {
        $this->attendance = $room;
    }

    /**
     * Get room
     *
     * @return $room
     */
    public function getRoom() {
        return $this->room;
    }

}
