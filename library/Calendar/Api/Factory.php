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

use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Alaress\Database\Connector\IConnector;
use Schoolbox\External\Connector\Factory as ExternalConnectorFactory;


class Calendar_Api_Factory
{
    /**
     * A list of all the possible event types to display
     *
     * @var array
     */
    protected $allTypes = array();

    /**
     * A data source for assessments.
     *
     * @var AssessmentDataSource
     */
    protected $assessmentDataSource;

    /**
     * Constructor
     *
     * @param AssessmentDataSource $assessmentDataSource
     * @param  IConnector $databaseExternal
     */
    public function __construct(AssessmentDataSource $assessmentDataSource, IConnector $databaseExternal)
    {
        $this->assessmentDataSource = $assessmentDataSource;
        $this->databaseExternal = $databaseExternal;

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
     * @param \Schoolbox\User $user
     * @return Calendar_Api_Composite
     */
    public function produceForUser(\Schoolbox\User $user)
    {
        $compositeApi = new Calendar_Api_Composite();

        foreach ($this->getAllTypes() as $config) {
            try {
                $compositeApi->add($this->produceFromConfig($config, $user));
            } catch (DomainException $e) {
                // Calendar API has an issue. Skip to next.
                continue;
            }
            // InvalidArgumentException or ReflectionException: NOT caught
        }

        /* Throw error if no APIs were determined and found
        if (!$compositeApi->getCount()) {
            throw new Exception('No Calendar APIs found for your implementation.');
        }
         */

        return $compositeApi;
    }

    /**
     * Handle unique construction logic for each variant of the calendar connector.
     *
     * @param Calendar_Api_Config_Record $config
     * @param \Schoolbox\User            $user
     * @return Calendar_Api_EventType_Abstract
     * @throws InvalidArgumentException if the item can't be built
     * @throws ReflectionException could be thrown by the Reflection API
     */
    public function produceFromConfig($config, \Schoolbox\User $user)
    {
        $base    = 'Calendar_Api_EventType_' . $config->getEventType() . '_' . $config->getConnectorClass();
        $default = $base . '_Default';
        $unique  = $base . '_' . ucwords(Cfg::get('site_location'));

        switch ($default) {
            case 'Calendar_Api_EventType_Submission_Schoolbox_Default':
                // Same as default constructor, but add the assessment data source
                $params = [$user, $config, $this->assessmentDataSource,];
                break;

            case 'Calendar_Api_EventType_Event_External_Default':
                // Same as default constructor, but add the assessment data source
                $factory = new ExternalConnectorFactory();
                $externalEvents = $factory->build(new \Schoolbox\Config\Loader(), $this->databaseExternal, ExternalConnectorFactory::EXTERNAL_EVENTS);
                $params = [$user, $config, $externalEvents,];
                break;

            default:
                $params = [$user, $config,];
                break;
        }

        foreach ([$unique, $default] as $className) {
            if (class_exists($className)) {
                $class          = new ReflectionClass($className);
                $implementation = $class->newInstanceArgs($params);
                break;
            }
        }

        if (!(isset($implementation) && $implementation instanceof Calendar_Api_EventType_Abstract)) {
            throw new InvalidArgumentException('No default or unique implementation found for '.$base);
        }

        $implementation->setConnectorId($config->getId());
        return $implementation;
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
}
