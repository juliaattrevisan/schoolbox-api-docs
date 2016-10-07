<?php

/**
 * ProjectTrait
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Project\Decorator;

trait ProjectTrait
{

    /**
     * @var int
     */
    protected $summator;

    /**
     * @return int
     */
    protected function getSummator()
    {
        return $this->summator;
    }

    /**
     * @param int $summator
     * @return $this
     */
    protected function setSummator($summator)
    {
        $this->summator = $summator;
        return $this;
    }
}