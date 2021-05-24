<?php

/**
 * Calendar_Event_Type_Day_Renderer_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\Day\Renderer;

use Calendar\Event\Render\CSVTrait;

class Csv extends Base
{
    /**
     * Inherit default CSV formatting
     * render()
     */
    use CSVTrait;
}
