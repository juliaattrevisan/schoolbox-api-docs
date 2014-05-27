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
 * Class Instance
 *
 * @deprecated
 * @package Schoolbox\Notif\Message\Instance
 */
class Instance {

    protected $params   = array();
    protected $severity = 0;
    protected $topics   = array();
    protected $targets  = array();
    protected $context  = array();

    /**
     * @deprecated
     * @param array $params
     * @param int $severity
     * @param array $topics
     * @param array $targets
     * @param array $context
     */
    function __construct($params, $severity, $topics, $targets = array(), $context = array()) {
        $this->params = $params;
        $this->severity = $severity;
        $this->topics = $topics;
        $this->targets = $targets;
        $this->context = $context;
    }

	/**
	 * @return array
	 */
	public function getParams() {
        return $this->params;
    }

	/**
	 * @param $targets
	 * @return self
	 */
	public function setTargets($targets) {
        $this->targets = $targets;
		return $this;
    }

	/**
	 * @return array
	 */
	public function getTargets() {
        return $this->targets;
    }

	/**
	 * @return array
	 */
	public function getTopics() {
        return $this->topics;
    }

	/**
	 * @return int
	 */
	public function getSeverity() {
        return $this->severity;
    }

	/**
	 * @return array
	 */
	public function getContext() {
        return $this->context;
    }

	/**
	 * @param int $severity
	 * @return self
	 */
	public function setSeverity($severity) {
		$this->severity = $severity;
		return $this;
	}

	/**
	 * @param array $topics
	 * @return self
	 */
	public function setTopics($topics) {
		$this->topics = $topics;
		return $this;
	}





    /**
     * @param array $context
     */
    public function setContext($context) {
        if(is_array($context)) {
            if(count($context)) {
                $this->context = array_merge($this->context, $context);
            }
            else {
                $this->context = array();
            }
        }
    }

} 