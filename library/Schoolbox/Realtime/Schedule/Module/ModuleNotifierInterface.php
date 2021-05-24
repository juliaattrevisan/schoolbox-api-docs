<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Module;

use Psr\Log\LoggerInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\NotifInterface;
use Schoolbox\Realtime\Message\NS;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Serializable;

interface ModuleNotifierInterface extends Serializable
{

    const IMMEDIATELY = 0;
    const ONE_HOUR    = 3600;
    const ONE_DAY     = 86400;
    const ONE_WEEK    = 604800;

    /**
     * @param IConfigLoader $configLoader
     */
    public function __construct($configLoader);

    /**
     * Switch logging on
     *
     * @param LoggerInterface $log
     * @param bool            $allowPropagation  whether or not the notifier should propagate messages in this context
     * @return void
     */
    public function enableLogging(LoggerInterface $log, $allowPropagation);

    /**
     * Instantiate all your dependencies within yourself.
     *
     * @param mixed $dependencies
     * @return void
     */
    public function loadDependencies($dependencies = null);


    /**
     * @param TriggerBundleInterface $bundle
     * @return void
     */
    public function bundleTriggers($bundle);


    /**
     * Return the list of callbacks that are associated with this trigger.
     *
     * @param string $label
     * @return array
     */
    public function retrieveCallbacks($label);


    /**
     * Return the message namespace this module conforms to.
     *
     * @return NS
     */
    public function getNamespace();
}
