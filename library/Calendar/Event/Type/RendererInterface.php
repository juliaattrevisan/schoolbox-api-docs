<?php

/**
 * This provides the interface for all renderers that are employed by events to use
 * Possible implementations include: json, vcal, csv
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type;

interface RendererInterface
{

    /**
     * Returns the output from the render action for this event
     *
     * @return string
     */
    public function render(): string;
}
