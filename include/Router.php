<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Router {

    private $route;
    private $reroutes;

    protected $class_base = '';
    protected $use_namespace_classes = false;

    public function __construct() {
        $this->route = array();
        $this->reroutes = array();
    }

    /**
     * @param $url
     * @throws Exception_Schoolbox
     */
    public function parseURL($url) {

        /* Only allow certain characters to appear in a url
         * Any illegal characters resets controller names, method names, method args and view names to empty
         * This can eventually land you back at the whale
         */
        if (preg_match("/[^a-zA-Z0-9\/\-\_\.@]+/", $url)) {
            throw new Exception_Schoolbox("Invalid URL");
        }

        try {

            // this $url is in the format : bundle/module/method[/args...][.view]
            $args = array_values(array_filter(explode('/', $url)));

            // find last position of period in the final argument
            $dot = strripos($args[count($args) - 1], '.');

            if($dot !== false) {

                $this->setViewName(substr($args[count($args) - 1], $dot + 1));
                $args[count($args) - 1] = substr($args[count($args) - 1], 0, $dot);
            }

            // period is not present in the final argument
            else {
                $this->setViewName('default');
            }

            $cName = Mediator::concatenate(array($args[0], 'API'), true, false);

            $mIndex = 3;

            // if a Bundle-level API is present, give it preference
            if(class_exists($cName)) {
                $mName = Mediator::concatenate($args[1], false);

                // only if the method exists also
                if(method_exists(new $cName(''), $mName)) {
                    $this->setClassName(Mediator::concatenate($args[0]));
                    $mIndex = 2;
                }
            }

            // if we didn't already specify a classname
            if($mIndex == 3) {
                // args[0] and [1] form the namespaced class, args[2] is the method
                $this->setClassName(Mediator::concatenate(array(
                    Mediator::concatenate($args[0], false),
                    Mediator::concatenate($args[1], false)
                ), true, false));
            }

            $this->setMethodName(Mediator::concatenate($args[$mIndex - 1], false));

            // use remaining arguments to form method arguments
            $mArgs = array();
            for($i = $mIndex; $i < count($args); $i++) {
                $mArgs[] = $args[$i];
            }

            $this->setMethodArgs($mArgs);

        } catch (ErrorException $e) {
            $this->route = array('cn' => '_', 'mn' => '', 'ma' => '', 'vn' => '');
        }

        // perform any rerouting that may be necessary
        $this->reroute();
    }


    /* ROUTING FUNCTIONS */

    public function registerReroute($origin, $dest) {

        // destination needs to consist of class and method
        if(!(is_array($dest) && count($dest) == 2)) {
            return;
        }

        // if the origin is an array, it must be class and method only
        if(is_array($origin)) {
            if(count($origin) != 2) {
                return;
            }
        }
        else {
            $origin = array($origin, '*');
        }

        $this->reroutes[$origin[0]][$origin[1]]['cn'] = $dest[0];
        $this->reroutes[$origin[0]][$origin[1]]['mn'] = $dest[1];

    }

    public function reroute() {

        $c = $this->getClassName(false);
        $m = $this->getMethodName();

        // does reroutes has $c as a key?
        if(isset($this->reroutes[$c])) {

            // is the specified method specified?
            if(isset($this->reroutes[$c][$m])) {
                $cn = $this->reroutes[$c][$m]['cn'];
                $mn = $this->reroutes[$c][$m]['mn'];
            }

            // failing that, is a wildcard specified?
            elseif(isset($this->reroutes[$c]['*'])) {
                $cn = $this->reroutes[$c]['*']['cn'];
                $mn = $this->reroutes[$c]['*']['mn'];
            }

            // neither scenario applies: no reroute
            else {
                return;
            }

            $this->setClassName($cn);
            $this->setMethodName($mn);
            $this->setMethodArgs(array());

        }
    }

    /* GETTERS AND SETTERS */

    public function getClassName($withSuffix = true) {
        return $this->route['cn'] . ($withSuffix ?  '_API' : '');
    }

    public function getMethodName() {
        return $this->route['mn'];
    }

    public function getMethodArgs() {
        return $this->route['ma'];
    }

    public function getViewName() {
        return $this->route['vn'];
    }

    private function setClassName($className) {
        $this->route['cn'] = $className;
    }

    private function setMethodName($methodName) {
        $this->route['mn'] = $methodName;
    }

    private function setMethodArgs($methodArgs) {
        $this->route['ma'] = $methodArgs;
    }

    private function setViewName($viewName) {
        $this->route['vn'] = $viewName;
    }

    /**
     * @param  string $class_base
     *
     * @return $this
     */
    public function setClassBase($class_base) {
        $this->class_base = $class_base;
        return $this;
    }

    /**
     * @return string
     */
    public function getClassBase() {
        return $this->class_base;
    }

    /**
     * @param  boolean $use_namespace_classes
     *
     * @return $this
     */
    public function setUseNamespaceClasses($use_namespace_classes) {
        $this->use_namespace_classes = $use_namespace_classes;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getUseNamespaceClasses() {
        return $this->use_namespace_classes;
    }


}
