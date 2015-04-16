<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Message;


use Schoolbox\Role;

class RoutePermission {

	/** @var Source */
	protected $msgSource;
	/** @var int */
	protected $role;
    /** @var array */
    protected $default;
    /** @var array */
    protected $mask;

    /**
     * @deprecated use $mask
     * @var int
     */
	protected $minLevel;
    /**
     * @deprecated use $mask
     * @var int
     */
	protected $maxLevel;
    /**
     * @deprecated use $default
     * @var int
     */
	protected $defaultLevel;

	/**
     * @deprecated use setDefault()
	 * @param int $defaultLevel
	 */
	public function setDefaultLevel($defaultLevel) {
		$this->defaultLevel = $defaultLevel;
	}

	/**
	 * @deprecated use getDefault()
	 * @return int
	 */
	public function getDefaultLevel() {
		return $this->defaultLevel;
	}

	/**
	 * @deprecated use setDefault()
	 * @param int $maxLevel
	 */
	public function setMaxLevel($maxLevel) {
		$this->maxLevel = $maxLevel;
	}

	/**
	 * @deprecated use getMask()
	 * @return int
	 */
	public function getMaxLevel() {
		return $this->maxLevel;
	}

	/**
	 * @deprecated use setMask()
	 * @param int $minLevel
	 */
	public function setMinLevel($minLevel) {
		$this->minLevel = $minLevel;
	}

	/**
	 * @deprecated use getMask()
	 * @return int
	 */
	public function getMinLevel() {
		return $this->minLevel;
	}

    /**
     * @return array
     */
	public function getLevels() {
		return array(
			'max'     => $this->getMaxLevel(),
			'default' => $this->getDefaultLevel(),
			'min'     => $this->getMinLevel()
		);
	}

	/**
	 * @param Source $msgSource
	 */
	public function setMsgSource($msgSource) {
		$this->msgSource = $msgSource;
	}

	/**
	 * @return Source
	 */
	public function getMsgSource() {
		return $this->msgSource;
	}

	/**
	 * @param Role $role
	 */
	public function setRole($role) {
		$this->role = $role;
	}

	/**
	 * @return Role
	 */
	public function getRole() {
		return $this->role;
	}


    /**
     * @return array
     */
    public function getDefault()
    {
        return $this->default;
    }


    /**
     * @param array $default
     * @return static
     */
    public function setDefault($default)
    {
        $this->default = $default;
        return $this;
    }


    /**
     * @return array
     */
    public function getMask()
    {
        return $this->mask;
    }


    /**
     * @param array $mask
     * @return static
     */
    public function setMask($mask)
    {
        $this->mask = $mask;
        return $this;
    }



} 