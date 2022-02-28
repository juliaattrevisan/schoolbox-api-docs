<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\Fixtures\Renderer;

use Calendar\Event\Render\CSVTrait;

class Csv extends Base
{
    /**
     * Inherit default CSV formatting
     * render()
     */
    use CSVTrait;
}
