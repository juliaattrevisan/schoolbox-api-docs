<?php

/**
 * Output handlnig class that renders xml
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Web\Output;

class Xml extends Html
{

    /**
     * Set the content-type
     */
    public function preprocessOutput()
    {
        $this->getResponse()->headers->set('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
