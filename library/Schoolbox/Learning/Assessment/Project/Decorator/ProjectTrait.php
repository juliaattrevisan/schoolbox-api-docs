<?php

/**
 * ProjectTrait
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Project\Decorator;

trait ProjectTrait
{

    /**
     * @var int
     */
    protected $summator;

    /**
     * @return string
     */
    protected function getSummator()
    {
        return $this->summator;
    }

    /**
     * @param string $summator
     * @return $this
     */
    protected function setSummator($summator)
    {
        $this->summator = $summator;
        return $this;
    }
}
