<?php
/**
 * Calendar_Event_Type_Day_Renderer_Default
 *
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Calendar\Event\Type\EOTC\Renderer;


use Calendar\Event\Type\RendererBase;

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