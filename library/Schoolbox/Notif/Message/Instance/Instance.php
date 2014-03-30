<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Schoolbox\Notif\Message\Instance;


class Instance {

    protected $params   = array();
    protected $severity = 0;
    protected $topics   = array();
    protected $targets  = array();
    protected $context  = array();

    /**
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

    public function getParams() {
        return $this->params;
    }

    public function setTargets($targets) {
        $this->targets = $targets;
    }

    public function getTargets() {
        return $this->targets;
    }

    public function getTopics() {
        return $this->topics;
    }

    public function getSeverity() {
        return $this->severity;
    }

    public function getContext() {
        return $this->context;
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