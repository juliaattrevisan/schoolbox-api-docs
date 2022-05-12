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
     * @var ?int
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
     */
    public function encodeConfig(Calendar_Api_Config_Connector_Abstract $config): self
    {
        $this->setConfig(base64_encode(serialize($config)));
        return $this;
    }


    /**
     * Decode config object when retrieving from db
     *
     * @return Calendar_Api_Config_Connector_Abstract
     */
    public function decodeConfig(): Calendar_Api_Config_Connector_Abstract
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
