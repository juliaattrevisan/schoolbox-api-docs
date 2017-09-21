<?php

/**
 * Helper
 *
 * This contains a mixed bag of extra functionality that doesn't really belong in Liss/Server.php
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace External\Liss;
use Schoolbox\Config\Loader as ConfigLoader;

class Helper
{
    /**
     * Source Name
     */
    const EVENT_SOURCE_NAME = 'liss';

    /**
     * @var ConfigLoader
     */
    private $configLoader;


    /**
     * Helper constructor.
     * @param ConfigLoader $configLoader
     */
    public function __construct(ConfigLoader $configLoader)
    {
        $this->setConfigLoader($configLoader);
    }

    /**
     * Generate the source_name value used in event_status table
     *
     * @return string
     */
    public function generateSourceName()
    {
        return self::EVENT_SOURCE_NAME . '_' . $this->getConfigLoader()->get('external_liss_schoolcode');
    }

    /* Getters/Setters
     */

    /**
     * @return ConfigLoader
     */
    public function getConfigLoader()
    {
        return $this->configLoader;
    }

    /**
     * @param ConfigLoader $configLoader
     * @return $this
     */
    public function setConfigLoader($configLoader)
    {
        $this->configLoader = $configLoader;
        return $this;
    }
}