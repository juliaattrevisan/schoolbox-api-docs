<?php

/**
 * Calendar_Api_Config_Mapper
 *
 * This maps configurations stored in the db to a configuration object which
 * is then injected into API classes
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Api_Config_Mapper extends Schoolbox\Mapper
{

    /**
     * Mapping rules
     *
     * @return Bool|void
     */
    protected function mapping()
    {
        $this->hasTable('calendar_connector');
        $this->hasModel('Calendar_Api_Config_Record');

        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true
            )
        );

        $this->hasMap(
            array(
                'column'   => 'event_type',
                'property' => 'eventType',
                'type'     => self::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'connector_class',
                'property' => 'connectorClass',
                'type'     => self::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'config',
                'property' => 'config',
                'type'     => self::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'status',
                'property' => 'status',
                'type'     => self::TYPE_INTEGER,
            )
        );
    }

    /**
     * Return a set of config records being used
     *
     * Un-uniqueified because this was ramming all vCal sources into the same
     * connector, and they should be unique (for filtering by source).
     *
     * @return \Schoolbox\Collection
     */
    public function getAllConfigRecords()
    {
        return $this->findByColumn('status', 1);
    }

    /**
     * Retrieves a list of sources for the calendar.
     *
     * @return array
     *   name: a name for this source
     *   id:   a comma-separated list of source IDs
     */
    public function getSourceListForCalendar()
    {
        $configs    = $this->getAllConfigRecords();
        $sourceList = $configs->map(
            function ($record) {
                /** @var Calendar_Api_Config_Record $record */
                return array(
                    'id'   => $record->getId(),
                    'name' => $record->decodeConfig()->getLabel()
                );
            }
        )->getAll();

        // Multiple sources can have identical names - join their IDs together.
        $sourceList = array_reduce(
            $sourceList,
            function ($carry, $item) {
                $id   = $item['id'];
                $name = $item['name'];
                if (!array_key_exists($name, $carry)) {
                    $carry[$name]       = $item;
                    $carry[$name]['id'] = array();
                }
                $carry[$name]['id'][] = $id;
                return $carry;
            },
            array()
        );

        // Join the IDs for each source together.
        $sourceList = array_map(
            function ($v) {
                $v['id'] = implode(',', $v['id']);
                return $v;
            },
            $sourceList
        );
        return array_values($sourceList);
    }

    /**
     * Return a set of decoded config objects for the specified event_type and connector_class
     *
     * @param $currentEventType
     * @param $currentConnector
     *
     * @return array
     */
    public function getAllConfigs($currentEventType, $currentConnector)
    {
        $configs = Calendar_Api_Config_Mapper::getInstance()->findByColumnList(
            array(
                'event_type'      => $currentEventType,
                'connector_class' => $currentConnector,
                'status'          => 1
            )
        );

        $allConfigs = array();

        foreach ($configs as $currentConfig) {
            /* @var $currentConfig Calendar_Api_Config_Record */
            $config = $currentConfig->decodeConfig();
            $config->setId($currentConfig->getId());

            $allConfigs[] = $config;
        }

        return $allConfigs;
    }

    /**
     * Deactivate all configs (used when updating)
     */
    public function deactivateAll()
    {
        $statement = $this->database->prepare(
            "
            UPDATE `{$this->table}`
            SET status = 0
        "
        );

        $statement->execute();

        return $this;
    }

    /**
     * Activate a particular connector
     *
     * @param $eventType
     * @param $connectorClass
     *
     * @return \Calendar_Api_Config_Mapper
     */
    public function activate($eventType, $connectorClass)
    {
        $statement = $this->database->prepare(
            "
            UPDATE `{$this->table}`
            SET status = 1
            WHERE event_type = :eventType
            AND connector_class = :connectorClass
        "
        );

        $statement->bindParam(':eventType', $eventType);
        $statement->bindParam(':connectorClass', $connectorClass);

        $statement->execute();

        return $this;
    }

    /**
     * Generate a tree of EventType/Connector pairs
     *
     * @return array
     */
    public function generateApiHierarchy()
    {

        /* Figger out which Event Types are around, and which Connectors
         * are available for each Event Type.
         */
        $path = '../library/Calendar/Api/EventType';

        $allApi = array();

        /* Just get the dir names (these correspond to event types)
         */
        foreach (array_filter(glob("$path/*"), 'is_dir') as $dirname) {
            $eventTypeFilename = basename($dirname);

            /* Path to connectors
             */
            $subpath = "$path/$eventTypeFilename";

            foreach (array_filter(glob("$subpath/*"), 'is_dir') as $subdirname) {
                $connectorClassFilename = basename($subdirname);

                /* Augment that array
                 */
                $allApi[$eventTypeFilename][] = $connectorClassFilename;
            }
        }

        return $allApi;
    }

    /**
     * Some generic test values used to populate the calendar_connector table
     * while developing the admin ui
     */
    public function setupDefaults()
    {
        $start = 85;

        $config = new Calendar_Api_Config_Connector_Vcal();
        $config->setId($start);
        $config->setEventType('Event');
        $config->setConnectorClass('Vcal');
        $config->setUrl('http://www.google.com');

        $configRecord = new Calendar_Api_Config_Record();
        $configRecord->setEventType('Event');
        $configRecord->setConnectorClass('Vcal');
        $configRecord->updateConfig($config);

        $this->save(array($configRecord));

        $config = new Calendar_Api_Config_Connector_Vcal();
        $config->setId($start++);
        $config->setEventType('Event');
        $config->setConnectorClass('Vcal');
        $config->setUrl('http://www.gruber.org');

        $configRecord = new Calendar_Api_Config_Record();
        $configRecord->setEventType('Event');
        $configRecord->setConnectorClass('Vcal');
        $configRecord->updateConfig($config);

        $this->save(array($configRecord));

        $config = new Calendar_Api_Config_Connector_Schoolbox();
        $config->setId($start++);
        $config->setEventType('Submission');
        $config->setConnectorClass('Schoolbox');

        $configRecord = new Calendar_Api_Config_Record();
        $configRecord->setEventType('Submission');
        $configRecord->setConnectorClass('Schoolbox');
        $configRecord->updateConfig($config);

        $this->save(array($configRecord));
    }
}
