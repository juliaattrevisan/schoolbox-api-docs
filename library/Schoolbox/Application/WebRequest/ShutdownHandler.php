<?php
/*
 * Wrapper to handle registration of shutdown functions and allow ordering of execution
 */

namespace Schoolbox\Application\WebRequest;

class ShutdownHandler
{

    private static $instance = false;
    private        $enabled  = true;
    private        $functions;

    private function __construct()
    {
        $this->functions = [];
    }

    public static function instance()
    {
        if (self::$instance == false) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function onShutdown()
    {

        // If the shutdown handler has been disabled don't execute any registered functions

        if (!$this->enabled) {
            return;
        }

        // Sort the array of shutdown functions by their key so that we execute them in correct priority order

        ksort($this->functions);

        foreach ($this->functions as $priority => $functions) {
            foreach ($functions as $function) {
                $function();
            }
        }
    }

    public function setEnabled($value)
    {
        $this->enabled = (bool)$value;
    }

    public function getEnabled()
    {
        return $this->enabled;
    }

    public function unregisterFunction($priority)
    {
        // Allow cancelling of all functions with a given priority number
        unset($this->functions[$priority]);
    }

    public function registerFunction(callable $fnc, $priority = PHP_INT_MAX)
    {
        // Each shutdown function is given a priority number. If none is supplied it just gets dumped into
        // the lowest priority possible. Functions will be executed in this order.

        if (!isset($this->functions[$priority])) {
            $this->functions[$priority] = [];
        }

        $this->functions[$priority][] = $fnc;
    }
}
