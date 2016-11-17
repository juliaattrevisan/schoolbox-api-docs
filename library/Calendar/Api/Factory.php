<?php

/**
 * Calendar_Api_Factory
 *
 * Creates an API composite.
 *
 * Produce a stack of API classes both based on school domain and
 * data source. Uses the Composite pattern to treat an amalgamation
 * of APIs as a single API class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Api_Factory
{

    /**
     * A list of all the possible event types to display
     *
     * @var array
     */
    protected $allTypes = array();

    /**
     * An instance of Schoolbox\User
     *
     * @var null
     */
    protected $user = null;

    /**
     * Constructor
     */
    public function __construct($user)
    {
        $this->setUser($user);

        $allConfigRecords = Calendar_Api_Config_Mapper::getInstance()->getAllConfigRecords();

        /* These configs are base64-encode()'d to get around a weird issue with unserialize
         * http://stackoverflow.com/questions/4264270/unserialize-sometimes-returns-false
         */
        foreach ($allConfigRecords as $currentConfig) {

            /** @var Calendar_Api_Config_Record $currentConfig */
            try {
                $config = $currentConfig->decodeConfig();

                /* Update $config so it knows the calendar_connector id from whence it came
                 * This is used in produce() and passed to each unique/default implementation
                 */
                $config->setId($currentConfig->getId());

                $this->addConfig($config);
            } catch (DomainException $e) {
                //Calendar not enabled, dont add
            }
        }
    }

    /**
     * Produce an API Composite
     *
     * @return Calendar_Api_Composite
     */
    public function produce()
    {
        $compositeApi = new Calendar_Api_Composite();

        foreach ($this->getAllTypes() as $config) {
            try {
                $defaultImplementation = 'Calendar_Api_EventType_' . $config->getEventType() . '_' . $config->getConnectorClass() . '_Default';
                $uniqueImplementation = 'Calendar_Api_EventType_' . $config->getEventType() . '_' . $config->getConnectorClass() . '_' . ucwords(Cfg::get('site_location'));

                /* Decide which to use (unique or default)
                 *
                 * Inject config settings, including the calendar_connector id
                 * so it knows from whence it came
                 */
                /** @var Calendar_Api_EventType_Abstract $implementation */
                if (class_exists($uniqueImplementation)) {
                    $implementation = new $uniqueImplementation($this->getUser(), $config);
                    $implementation->setConnectorId($config->getId());

                    $compositeApi->add($implementation);
                } else {
                    if (class_exists($defaultImplementation)) {
                        $implementation = new $defaultImplementation($this->getUser(), $config);
                        $implementation->setConnectorId($config->getId());

                        $compositeApi->add($implementation);
                    }
                }
            } catch (DomainException $e) {
                // Calendar API has an issue. Skip to next.
            }
        }

        /* Throw error if no APIs were determined and found
        if (!$compositeApi->getCount()) {
            throw new Exception('No Calendar APIs found for your implementation.');
        }
         */

        return $compositeApi;
    }

    /**
     * Empty list of configs
     */
    public function resetConfigs()
    {
        $this->allTypes = array();

        return $this;
    }

    /**
     * Use only preselected configs
     *
     * @param $config
     *
     * @return \Calendar_Api_Factory
     */
    public function addConfig($config)
    {
        $this->allTypes[] = $config;

        return $this;
    }

    /* Getters/Setters
     */

    /**
     * Get all types
     *
     * @return array
     */
    protected function getAllTypes()
    {
        return $this->allTypes;
    }

    /**
     * Set user
     *
     * @param null $user
     */
    private function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * Get user
     *
     * @return null
     */
    private function getUser()
    {
        return $this->user;
    }
}
