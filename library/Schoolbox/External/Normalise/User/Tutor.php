<?php

/**
 * Tutor
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\User;

class Tutor extends Staff
{

    /**
     * Tutor group
     *
     * @var string
     */
    protected $group = '';

    /* Getters/Setters
     */

    /**
     * Set group
     *
     * @param string $group
     */
    public function setGroup($group)
    {
        $this->group = $group;
    }

    /**
     * Get group
     *
     * @return string
     */
    public function getGroup()
    {
        return $this->group;
    }
}
