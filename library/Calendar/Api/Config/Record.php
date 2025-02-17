<?php

/**
 * Calendar_Api_Config_Record
 *
 * This corresponds to a row in the calendar_connector table and is used
 * by the Mapper class
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Calendar_Api_Config_Record
{
    /* An index of (event_type, connector_class) tuples and their corresponding
     * config rendering mechanisms. A manually curated list to avoid schema changes
     * whenever we need to fudge data.
     *
     * $configRendererIndex = [
     *     event_type => [
     *         connector_class => config_renderer
     *     ]
     * ]
     *
     * This is a hack employed here as safer alternative to a refactor that would require lots of testing.
     * Context: https://github.com/alaress/schoolbox/pull/28674
     */
    private const CONFIG_RENDERER_INDEX = [
        // Key: event_type from calendar_connection database table row.
        'Custom' => [
            // Key  : connector_class from same calendar_connection database table row.
            // Value: Match hard-coded $connectorClass from Api/Config/Connector/EOTC.php for default behaviour.
            //        Override to use different class in the same directory.
            'EOTC' => 'EOTC', // Default.
        ],
        'Event' => [
            'External' => 'External', // Default.
            'Schoolbox' => 'Schoolbox', // Default.
            'Vcal' => 'Vcal' // Default.
        ],
        'Fixtures' => [
            'Schoolbox' => 'Fixtures' // Override.
        ],
        'ResourceBooking' => [
            'Booking' => 'Booking', // Default.
            'Moderation' => 'Moderation' // Default.
        ],
        'Submission' => [
            'Schoolbox' => 'Schoolbox', // Default.
        ],
        'SudsEvent' => [
            'Schoolbox' => 'SudsEvent', // Override.
        ],
        'SudsTimetable' => [
            'Schoolbox' => 'SudsTimetable' // Override.
        ],
        'Timetable' => [
            'ExternalConstrained' => 'ExternalConstrained' // Default.
        ],
        'TimetableDays' => [
            'External' => 'External' // Default.
        ],
    ];

    /**
     * Status of a config record
     *
     * If all config records for a connector are inactive, that connector is deemed
     * inactive too. This is the equivalent of checking that checkbox next to the connector
     * in admin, but without having to delete the settings.
     */
    public const ACTIVE   = 1;
    public const INACTIVE = 0;

    /**
     * Primary key
     *
     * @var int|null
     */
    public ?int $id = null;

    /**
     * The kind of event this configuration is for
     * e.g. 'Event', 'Submission'
     *
     * Public because of Mapper
     *
     * @var string
     */
    public string $eventType = '';

    /**
     * Class name of the API which will use this config
     * e.g. 'Schoolbox', 'Vcal'
     *
     * Public because of Mapper
     *
     * @var string
     */
    public string $connectorClass = '';

    /**
     * Serialized configuration object
     *
     * @var string
     */
    public string $config = '';

    /**
     * Status of the config
     *
     * @var int
     */
    public int $status = 1;

    /**
     * count of times the calendar request has failed, if more than 5 times, display to the system config space
     * @var int
     */
    private int $failedCount = 0;
    public const MAXFAILED = 5;

    /* These configs are base64-encode()'d to get around a weird issue with unserialize
     * http://stackoverflow.com/questions/4264270/unserialize-sometimes-returns-false
     */

    /**
     * Update config object
     *
     * @param Calendar_Api_Config_Connector_Abstract $config
     * @return Calendar_Api_Config_Record
     */
    public function updateConfig(Calendar_Api_Config_Connector_Abstract $config): self
    {
        $this->encodeConfig($config);
        return $this;
    }

    /**
     * Encode config object for storage in db
     *
     * @param Calendar_Api_Config_Connector_Abstract $config
     * @return $this
     * @throws JsonException
     */
    public function encodeConfig(Calendar_Api_Config_Connector_Abstract $config): self
    {
        $configArray = [
            'label'       => $config->getLabel(),
            'luEventType' => $config->getLuEventType(),
        ];

        switch (get_class($config)) {
            case Calendar_Api_Config_Connector_ExternalConstrained::class:
                $configArray['duration'] = $config->getDuration();
                break;

            case Calendar_Api_Config_Connector_Vcal::class:
                $configArray['url']           = $config->getUrl();
                $configArray['authorization'] = $config->getAuthorization();
                $configArray['campusIds']     = $config->getCampusIds();
                $configArray['groupIds']      = $config->getGroupIds();
                break;

            default:
                // Do nothing.
        }

        $this->setConfig(json_encode($configArray, JSON_THROW_ON_ERROR));

        return $this;
    }

    /**
     * Decode config object when retrieving from db
     *
     * @return Calendar_Api_Config_Connector_Abstract
     */
    public function decodeConfig(): Calendar_Api_Config_Connector_Abstract
    {
        $configJson = $this->getConfig();

        try {
            $config = json_decode($configJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            // If we cannot decode the JSON-encoded config, start with an empty array.  We do this so that the user can
            // still edit and fix the calendar connector config on page https://vagrant.dev.schoolbox.cloud/adminv2/calendar/Subscribe,
            // even if the JSON config data has become corrupted for whatever reason.  Handling the JsonException here
            // also allows for simpler code in calling functions.
            trigger_error(
                // Even though we are handling this exception, we still want to log when it happens.
                "JSON string could not be decoded - empty object created instead. (JSON string: '$configJson')",
                E_USER_WARNING
            );
            $config = [];
        }

        $configRenderer = self::CONFIG_RENDERER_INDEX[$this->getEventType()][$this->getConnectorClass()];

        // Instantiate class object given description in $config;
        $className = 'Calendar_Api_Config_Connector_' . $configRenderer;
        $obj       = new $className();

        // These fields are stored in the schoolbox.calendar_connector table in columns other than `config`.
        // Since we are instantiating a new object, we need data from both the `config` column and the other columns.
        $obj->setConnectorClass($this->getConnectorClass());
        $obj->setEventType($this->getEventType());
        $obj->setFailedCount($this->getFailedCount());
        $obj->setId($this->getId());

        // These fields are stored in schoolbox.calendar_connector as a JSON string in the `config` column.
        $obj->setLabel($config['label'] ?? '');
        $obj->setLuEventType($config['luEventType'] ?? 1); // This refers to database table schoolbox.luEventType.

        switch (get_class($obj)) {
            case Calendar_Api_Config_Connector_ExternalConstrained::class:
                // These fields are stored in schoolbox.calendar_connector as a JSON string in the `config` column.
                $obj->setDuration($config['duration'] ?? '');
                break;

            case Calendar_Api_Config_Connector_Vcal::class:
                // These fields are stored in schoolbox.calendar_connector as a JSON string in the `config` column.
                $obj->setUrl($config['url'] ?? '');
                $obj->setAuthorization($config['authorization'] ?? '');
                $obj->setCampusIds($config['campusIds'] ?? []);
                $obj->setGroupIds($config['groupIds'] ?? []);
                break;

            default:
                // Do nothing.
        }

        return $obj;
    }

    /**
     * Encode config object for storage in db
     * This is the pre-JSON encoding method, preserved for legacy purposes.
     *
     * @deprecated
     * @param Calendar_Api_Config_Connector_Abstract $config
     * @return $this
     */
    public function encodeConfigLegacy(Calendar_Api_Config_Connector_Abstract $config): self
    {
        $this->setConfig(base64_encode(serialize($config)));
        return $this;
    }

    /**
     * Decode config object when retrieving from db
     * This is the pre-JSON decoding method, preserved for legacy purposes.
     *
     * @deprecated
     * @return Calendar_Api_Config_Connector_Abstract
     */
    public function decodeConfigLegacy(): Calendar_Api_Config_Connector_Abstract
    {
        /** @var Calendar_Api_Config_Connector_Abstract $obj */
        $obj = unserialize(base64_decode($this->getConfig()), [Calendar_Api_Config_Connector_Abstract::class]);
        $obj->setFailedCount($this->getFailedCount());
        return $obj;
    }

    /**
     * Activate this config record
     * @return Calendar_Api_Config_Record
     */
    public function activate(): self
    {
        $this->setStatus(self::ACTIVE);
        return $this;
    }

    /**
     * Deactivate this config record, but not delete it
     * @return Calendar_Api_Config_Record
     */
    public function deactivate(): self
    {
        $this->setStatus(self::INACTIVE);
        return $this;
    }

    /**
     * This connector active?
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->getStatus() === self::ACTIVE;
    }

    /* Getters/Setters
     */

    /**
     * @param string|null $eventType
     * @return Calendar_Api_Config_Record
     */
    public function setEventType(?string $eventType): self
    {
        $this->eventType = $eventType;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    /**
     * Set connectorClass
     *
     * @param string|null $connectorClass
     * @return Calendar_Api_Config_Record
     */
    public function setConnectorClass(?string $connectorClass): self
    {
        $this->connectorClass = $connectorClass;
        return $this;
    }

    /**
     * Get connectorClass
     *
     * @return string|null
     */
    public function getConnectorClass(): ?string
    {
        return $this->connectorClass;
    }

    /**
     * Set config
     *
     * @param string|null $config
     * @return Calendar_Api_Config_Record
     */
    public function setConfig(?string $config): self
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Get config
     *
     * @return string|null
     */
    public function getConfig(): ?string
    {
        return $this->config;
    }

    /**
     * Set id
     *
     * @param int|null $id
     * @return Calendar_Api_Config_Record
     */
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get id
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set status
     *
     * @param int|null $status
     * @return Calendar_Api_Config_Record
     */
    public function setStatus(?int $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Get status
     *
     * @return int|null
     */
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /**
     *
     */
    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function setFailedCount(int $count)
    {
        $this->failedCount = $count;
    }

    public function addFailedCount(): int
    {
        $this->failedCount++;
        return $this->failedCount;
    }

    public function isDead()
    {
        return $this->failedCount >= self::MAXFAILED;
    }
}
