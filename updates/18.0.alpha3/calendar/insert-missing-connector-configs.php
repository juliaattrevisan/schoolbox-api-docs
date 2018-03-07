<?php
/**
 * Repair missing Resource Booking Calendar Configuration records
 *
 * Schools are sometimes missing the Booking or Moderation records in the calendar_connector table
 * While you can repair it via the ui, we'll just spawn the records here
 *
 * Missing records are always restored; however, their activation Status is determinate based on the Feature Flag
 * in config.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Mapper;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Create Missing Resource Booking Calendar Configs', [
    'verbose' => [false, false, false],
    'errors'  => E_ALL,
]);

$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {
    $db = Database::getInstance();

    /* Force respawn of missing records
     * The models are very powerful in their awareness of feature flags, so force-activate Resource Booking,
     * spawn the missing rows, then restore Resource Booking to its original state
     */
    $status = (int)$db->query("
        SELECT value 
        FROM `config`
        WHERE name = 'resource_booking_enabled'
        LIMIT 1
    ")->fetch()['value'];

    /* Forcibly activate Resource Booking
     */
    $db->query("
        UPDATE `config` 
        SET value = 1 WHERE name = 'resource_booking_enabled'
        LIMIT 1
    ");

    /* Install the Resource Booking "Booking" config if it doesn't already exist
     */
    $existing = (int)$db->query("
        SELECT COUNT(*) AS value
        FROM `calendar_connector`
        WHERE event_type = 'ResourceBooking'
        AND connector_class = 'Booking'
        LIMIT 1
    ")->fetch()['value'];

    if (!$existing) {
        $config = new Calendar_Api_Config_Connector_Booking();
        $config->setConnectorClass('Booking');
        $config->setId(-1);
        $config->setLabel('Resource Booking');
        $config->setEventType('ResourceBooking');
        $config->setLuEventType(1);

        /* Replaces TzozNzoiQ2FsZW5kYXJfQXBpX0NvbmZpZ19Db25uZWN0b3JfQm9va2luZyI6Njp7czoxNzoiACoAY29ubmVjdG9yQ2xhc3MiO3M6NzoiQm9va2luZyI7czo1OiIAKgBpZCI7aTotMTtzOjg6IgAqAGxhYmVsIjtzOjE2OiJSZXNvdXJjZSBCb29raW5nIjtzOjEyOiIAKgBldmVudFR5cGUiO3M6MTU6IlJlc291cmNlQm9va2luZyI7czoxNDoiACoAbHVFdmVudFR5cGUiO3M6MToiMSI7czoxNzoiACoAaGFzUGVybWlzc2lvbnMiO2I6MDt9
         */
        $newRecord = new Calendar_Api_Config_Record();
        $newRecord->setConfig($config);

        $db->query("
            INSERT INTO `calendar_connector` (event_type, connector_class, config, status) 
            VALUES (\"ResourceBooking\", \"Booking\", \"{$newRecord->encodeConfig()}\", \"{$status}\")
        ");
    }

    /* Install the Resource Booking "Moderation" config if it doesn't already exist
     */
    $existing = (int)$db->query("
        SELECT COUNT(*) AS value
        FROM `calendar_connector`
        WHERE event_type = 'ResourceBooking'
        AND connector_class = 'Moderation'
        LIMIT 1
    ")->fetch()['value'];

    if (!$existing) {
        $config = new Calendar_Api_Config_Connector_Moderation();
        $config->setConnectorClass('Moderation');
        $config->setId(-1);
        $config->setLabel('Resource Moderation');
        $config->setEventType('ResourceBooking');
        $config->setLuEventType(1);

        /* Replaces Tzo0MDoiQ2FsZW5kYXJfQXBpX0NvbmZpZ19Db25uZWN0b3JfTW9kZXJhdGlvbiI6Njp7czoxNzoiACoAY29ubmVjdG9yQ2xhc3MiO3M6MTA6Ik1vZGVyYXRpb24iO3M6NToiACoAaWQiO2k6LTE7czo4OiIAKgBsYWJlbCI7czoxOToiUmVzb3VyY2UgTW9kZXJhdGlvbiI7czoxMjoiACoAZXZlbnRUeXBlIjtzOjE1OiJSZXNvdXJjZUJvb2tpbmciO3M6MTQ6IgAqAGx1RXZlbnRUeXBlIjtzOjE6IjEiO3M6MTc6IgAqAGhhc1Blcm1pc3Npb25zIjtiOjA7fQ==
         */
        $newRecord = new Calendar_Api_Config_Record();
        $newRecord->setConfig($config);

        $db->query("
            INSERT INTO `calendar_connector` (event_type, connector_class, config, status) 
            VALUES (\"ResourceBooking\", \"Moderation\", \"{$newRecord->encodeConfig()}\", \"{$status}\")
        ");
    }

    /* Forcibly restore Resource Booking to its former glory
     */
    $db->query("
        UPDATE `config` 
        SET value = {$status} WHERE name = 'resource_booking_enabled'
        LIMIT 1
    ");

});