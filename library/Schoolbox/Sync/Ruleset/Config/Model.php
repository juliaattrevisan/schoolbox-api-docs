<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Ruleset\Config;

/**
 * Model for sync ruleset configuration.
 *
 * @package Schoolbox\Sync\Ruleset\Config
 */
class Model
{
    /**
     * Role type (and primary key) this config applies to.
     *
     * @var string
     */
    protected $roleType;

    /**
     * @var bool
     */
    protected $enabled;

    /**
     * @return string
     */
    public function getRoleType()
    {
        return $this->roleType;
    }

    /**
     * @param  string $roleType
     *
     * @return $this
     */
    public function setRoleType($roleType)
    {
        $this->roleType = $roleType;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param  boolean $enabled
     *
     * @return $this
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
        return $this;
    }
}
