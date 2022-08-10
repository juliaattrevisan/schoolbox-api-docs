<?php

/**
 * Calendar_Api_Config_Mapper
 *
 * This maps configurations stored in the db to a configuration object which
 * is then injected into API classes
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Calendar\Api\ModuleChecker;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;

/**
 * @method Calendar_Api_Config_Record createModel()
 * @method Calendar_Api_Config_Record findOneByColumnList($columnList, array $options = [])
 * @method Collection|Calendar_Api_Config_Record[] findByColumnList($columnList, $options = [])
 */
class Calendar_Api_Config_Mapper extends BaseMapper
{
    /**
     * Calendar configurations don't always exist for a calendar source
     *
     * Some calendar sources operate in a pre-set (hardcoded) manner that is devoid of choice
     * such as Resource Booking
     */
    public const CONFIGURATION_NONE = -1;

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
        $this->uses('failed_count', self::TYPE_INTEGER, null, 'failedCount');
    }

    /**
     * Return a set of config records being used
     *
     * Un-uniqueified because this was ramming all vCal sources into the same
     * connector, and they should be unique (for filtering by source).
     *
     * Has the ability to list the failed entries.
     * mostly used in admin or cli cron to find the sources that require to be re-enabled.
     * @param bool $includeFailed
     * @return Collection|Calendar_Api_Config_Record[]
     */
    public function getAllConfigRecords(bool $includeFailed = false)
    {
        $predicates = $this->getPredicateBuilder();
        $predicates->equals('status', 1);
        if (!$includeFailed) {
            //hide anything that has failed too many times, unless requested
            $predicates->lessThan('failed_count', Calendar_Api_Config_Record::MAXFAILED);
        }
        return $this->findByPredicates($predicates);
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
     * Find all the active timetable-related subscriptions
     *
     * @return \Schoolbox\GenericCollection
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function findActiveTimetableSubscriptions(): \Schoolbox\GenericCollection
    {
        /* This is a specific combination of tuples which isn't specifically
         * defined anywhere conveniently
         */
        $query = "
            SELECT {$this->getColumnString()}
            FROM {$this->getTable()}
            WHERE status = 1
            AND (
                (event_type = 'SudsTimetable' AND connector_class = 'Schoolbox') OR
                (event_type = 'Timetable' AND connector_class = 'ExternalConstrained') OR
                (event_type = 'TimetableDays' AND connector_class = 'External')
            )
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute();

        $stack = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $stack->add($this->hydrate($row));
        }

        return $stack;
    }

    /**
     * The provided eventType and connectorClass combination is an active source
     * in Calendar subscription
     *
     * @param string $eventType
     * @param string $connectorClass
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
     * @param ModuleChecker $moduleChecker
     * @param User          $user
     * @return array
     *   name: a name for this source
     *   id:   a comma-separated list of source IDs
     */
    public function getSourceListForCalendar(ModuleChecker $moduleChecker, User $user): array
    {
        $configs    = $this->getAllConfigRecords();
        $sourceList = [];

        foreach ($configs as $record) {
            $config = $record->decodeConfig();
            try {
                if (!$moduleChecker->isEnabled($config)) {
                    continue;
                }

                if (
                    !$config instanceof Calendar_Api_Config_Connector_Vcal ||
                    $config->hasPermissions(
                        $user->getCampusCollection()->getColumnValues('id'),
                        $user->getRole()->getId()
                    )
                ) {
                    $sourceList[] = [
                        'id'   => (int)$record->getId(),
                        'name' => $config->getLabel()
                    ];
                }
            } catch (\TypeError $e) {
                // I will not buy this record; it is scratched
            }
        }

        // Multiple sources can have identical names - join their IDs together.
        $sourceList = array_reduce(
            $sourceList,
            function ($carry, $item) {
                $id   = $item['id'];
                $name = $item['name'];
                if (!array_key_exists($name, $carry)) {
                    $carry[$name]        = $item;
                    $carry[$name]['ids'] = [];
                }
                $carry[$name]['ids'][] = $id;
                return $carry;
            },
            []
        );

        return array_values($sourceList);
    }

    /** @noinspection PhpDocRedundantThrowsInspection - MapperException masked by findByColumnList method annotation */
    /**
     * Return a set of decoded config objects for the specified event_type and connector_class
     *
     * @param string $currentEventType
     * @param string $currentConnector
     * @param ModuleChecker $moduleChecker
     * @return Calendar_Api_Config_Connector_Interface[]
     * @throws MapperException
     */
    public function getAllConfigs($currentEventType, $currentConnector, ModuleChecker $moduleChecker)
    {
        $configs = $this->findByColumnList([
            'event_type'      => $currentEventType,
            'connector_class' => $currentConnector,
            'status'          => 1
        ]);

        $allConfigs = [];

        foreach ($configs as $currentConfig) {
            $config = $currentConfig->decodeConfig();
            try {
                if ($moduleChecker->isEnabled($config)) {
                    $config->setId($currentConfig->getId());
                    $allConfigs[] = $config;
                }
            } catch (\TypeError $e) {
                // I will not buy this record; it is scratched
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
     * @param string $eventType
     * @param string $connectorClass
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
