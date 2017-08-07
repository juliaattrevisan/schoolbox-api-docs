<?php
/**
 * ConfigMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace EOTC;

use Calendar_Api_Config_Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted as MapperDeletedException;

class ConfigMapper extends \Schoolbox\Mapper
{
    const EOTC_SUBSCRIPTION_EVENT_TYPE = 'Custom';
    const EOTC_SUBSCRIPTION_CONNECTOR_CLASS = 'EOTC';

    /**
     * Mapping
     */
    public function mapping()
    {
        $this->hasTable('eotc_config');
        $this->hasModel(Config::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->uses('name', self::TYPE_STRING, self::COLUMN_IDENTIFIER, 'name');
        $this->uses('value', self::TYPE_STRING, self::COLUMN_IDENTIFIER, 'value');
    }

    /**
     * Determine if EOTC module enabled and subscription enabled
     * This grants you access to EOTC event data
     */
    public function isSubscriptionEnabled()
    {
        $moduleEnabled = \Config::get('eotc_enabled');
        $subscriptionEnabled = Calendar_Api_Config_Mapper::getInstance()->isSourceEnabled(self::EOTC_SUBSCRIPTION_EVENT_TYPE, self::EOTC_SUBSCRIPTION_CONNECTOR_CLASS);

        return $moduleEnabled && $subscriptionEnabled;
    }

    /**
     * Get urgent threshold
     *
     * @return Config
     * @throws MapperException
     * @throws MapperDeletedException
     */
    public function findUrgentThreshold()
    {
        return $this->findOneByColumn('name', 'urgent_threshold');
    }

    /**
     * Get first reminder threshold
     *
     * @return Config
     * @throws MapperException
     * @throws MapperDeletedException
     */
    public function findFirstReminderThreshold()
    {
        return $this->findOneByColumn('name', 'first_reminder');
    }

    /**
     * Get daily reminder threshold
     *
     * @return Config
     * @throws MapperException
     * @throws MapperDeletedException
     */
    public function findDailyReminderThreshold()
    {
        return $this->findOneByColumn('name', 'daily_reminder');
    }
}
