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
 */

use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * @method Calendar_Api_Config_Record findOneByColumnList($columnList, $order = [], $options = [])
 */
class Calendar_Api_Config_Mapper extends BaseMapper
{
    /**
     * Mapping rules
     */
    protected function mapping()
    {
        $this->hasTable('calendar_connector');
        $this->hasModel(Calendar_Api_Config_Record::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->uses('event_type', self::TYPE_STRING, null, 'eventType');
        $this->uses('connector_class', self::TYPE_STRING, null, 'connectorClass');
        $this->uses('config', self::TYPE_STRING, null, 'config');
        $this->uses('status', self::TYPE_INTEGER, null, 'status');
    }

    /**
     * Return a set of config records being used
     *
     * Un-uniqueified because this was ramming all vCal sources into the same
     * connector, and they should be unique (for filtering by source).
     *
     * @return Collection|Calendar_Api_Config_Record[]
     */
    public function getAllConfigRecords()
    {
        return $this->findByColumn('status', 1);
    }


    /**
     * Finds the one config model for the specified fully-qualified class name, or throws exception.
     *
     * WARNING: This *will* likely throw an exception if you attempt to find the ID of a VCal connector.
     *          The system may have more than one VCal connector configured.
     *
     * @param string $className The fully-qualified class name, eg 'Calendar_Api_EventType_Custom_EOTC_Default'
     * @return Calendar_Api_Config_Record
     */
    public function findOneForClass(string $className): Calendar_Api_Config_Record
    {
        // Strip common prefix and explode on '_' char
        [$eventType, $connectorClass] = explode('_', str_replace('Calendar_Api_EventType_', '', $className));
        return $this->findOneByColumnList(['event_type' => $eventType, 'connector_class' => $connectorClass]);
    }

    /**
     * The provided eventType and connectorClass combination is an active source
     * in Calendar subscription
     *
     * @param $eventType
     * @param $connectorClass
     * @return bool
     * @throws MapperException
     */
    public function isSourceEnabled($eventType, $connectorClass)
    {
        return $this->countByColumnList([
            'event_type'      => $eventType,
            'connector_class' => $connectorClass,
            'status'          => 1
        ]) > 0;
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
        $configs = $this->getAllConfigRecords();
        $sourceList = [];

        foreach ($configs as $record) {
            /** @var Calendar_Api_Config_Record $record */
            try {
                $sourceList[] = [
                    'id'   => $record->getId(),
                    'name' => $record->decodeConfig()->getLabel()
                ];
            } catch (DomainException $e) {
                //config does not exist or is disabled
            }
        }

        // Multiple sources can have identical names - join their IDs together.
        $sourceList = array_reduce(
            $sourceList,
            function ($carry, $item) {
                $id   = $item['id'];
                $name = $item['name'];
                if (!array_key_exists($name, $carry)) {
                    $carry[$name]       = $item;
                    $carry[$name]['id'] = [];
                }
                $carry[$name]['id'][] = $id;
                return $carry;
            },
            []
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
     * @return Calendar_Api_Config_Connector_Interface[]
     * @throws MapperException
     */
    public function getAllConfigs($currentEventType, $currentConnector)
    {
        $configs = Calendar_Api_Config_Mapper::getInstance()->findByColumnList([
            'event_type'      => $currentEventType,
            'connector_class' => $currentConnector,
            'status'          => 1
        ]);

        $allConfigs = [];

        foreach ($configs as $currentConfig) {
            try {
                /* @var $currentConfig Calendar_Api_Config_Record */
                $config = $currentConfig->decodeConfig();
                /** @var Calendar_Api_Config_Connector_Abstract $config */
                $config->setId($currentConfig->getId());

                $allConfigs[] = $config;
            } catch (DomainException $e) {
                //config does not exist or is disabled
            }
        }

        return $allConfigs;
    }

    /**
     * Deactivate all configs (used when updating)
     *
     * @return $this
     */
    public function deactivateAll()
    {
        $statement = $this->database->prepare("
            UPDATE `{$this->table}`
            SET status = 0
        ");

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
        $statement = $this->database->prepare("
            UPDATE `{$this->table}`
            SET status = 1
            WHERE event_type = :eventType
            AND connector_class = :connectorClass
        ");

        $statement->execute([
            'eventType' => $eventType,
            'connectorClass' => $connectorClass
        ]);

        return $this;
    }
}
