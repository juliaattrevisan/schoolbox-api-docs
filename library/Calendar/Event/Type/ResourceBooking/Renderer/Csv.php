<?php
/**
 * Calendar_Event_Type_Day_Renderer_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Calendar\Event\Type\ResourceBooking\Renderer;

class Csv extends Base {

    /**
     * Inherit default CSV formatting
     * render()
     */
    use CSVTrait;

}
