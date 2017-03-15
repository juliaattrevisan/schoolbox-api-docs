<?php
/**
 * This is the base renderer for day events,
 * it provides support functions for determining how to render a day event
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Calendar\Event\Type\External\Renderer;

use \Calendar\Event\Type\RendererBase;

abstract class Base extends RendererBase
{

    /**
     * @return bool
     */
    public function isVisible()
    {
        return true;
    }
}
