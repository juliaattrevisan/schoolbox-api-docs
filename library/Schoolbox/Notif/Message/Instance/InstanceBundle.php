<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Schoolbox\Notif\Message\Instance;


/**
 * Class InstanceBundle
 *
 * @deprecated
 * @package Schoolbox\Notif\Message\Instance
 */
class InstanceBundle {

	/**
	 * @deprecated
	 */
	function __construct() {
		// TODO: Implement __construct() method.
	}


	/** @var Instance[] */
    protected $instances;

    /**
     * @param Instance $instance
     */
    public function attach($instance) {
        $hash = sha1(json_encode(array(
	        $instance->getParams(),
	        $instance->getTopics(),
	        $instance->getTargets()
        )));
        $this->instances[$hash] = $instance;
    }

    /**
     * @return Instance[]
     */
    public function getInstances() {
        return $this->instances;
    }

}