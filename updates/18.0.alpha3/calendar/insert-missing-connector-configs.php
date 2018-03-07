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

    $status = (int)$db->query("
        SELECT value 
        FROM `config`
        WHERE name = 'resource_booking_enabled'
        LIMIT 1
    ")->fetch()['value'];

    /* Install the Resource Booking "Booking" config if it doesn't already exist
     */
    try {

        $mapper->findOneByColumnList([
            'event_type' => 'ResourceBooking',
            'connector_class' => 'Booking'
        ]);
    }
    catch (Mapper\Exception $e) {

        $config = new Calendar_Api_Config_Connector_Booking();
        $config->setConnectorClass('Booking');
        $config->setId(-1);
        $config->setLabel('Resource Booking');
        $config->setEventType('ResourceBooking');
        $config->setLuEventType(1);

        $newRecord = new Calendar_Api_Config_Record();
        $newRecord->setEventType('ResourceBooking');
        $newRecord->setConnectorClass('Booking');
        $newRecord->setStatus($status);

        /* Replaces TzozNzoiQ2FsZW5kYXJfQXBpX0NvbmZpZ19Db25uZWN0b3JfQm9va2luZyI6Njp7czoxNzoiACoAY29ubmVjdG9yQ2xhc3MiO3M6NzoiQm9va2luZyI7czo1OiIAKgBpZCI7aTotMTtzOjg6IgAqAGxhYmVsIjtzOjE2OiJSZXNvdXJjZSBCb29raW5nIjtzOjEyOiIAKgBldmVudFR5cGUiO3M6MTU6IlJlc291cmNlQm9va2luZyI7czoxNDoiACoAbHVFdmVudFR5cGUiO3M6MToiMSI7czoxNzoiACoAaGFzUGVybWlzc2lvbnMiO2I6MDt9
         */
        $newRecord->updateConfig($config);

        $mapper->save($newRecord);
    }

    /* Install the Resource Booking "Moderation" config if it doesn't already exist
     */
    try {
        Calendar_Api_Config_Mapper::getInstance()->findOneByColumnList([
            'event_type' => 'ResourceBooking',
            'connector_class' => 'Moderation'
        ]);
    }
    catch (Mapper\Exception $e) {
        $config = new Calendar_Api_Config_Connector_Moderation();
        $config->setConnectorClass('Moderation');
        $config->setId(-1);
        $config->setLabel('Resource Moderation');
        $config->setEventType('ResourceBooking');
        $config->setLuEventType(1);

        $newRecord = new Calendar_Api_Config_Record();
        $newRecord->setEventType('ResourceBooking');
        $newRecord->setConnectorClass('Moderation');
        $newRecord->setStatus($status);

        /* Replaces Tzo0MDoiQ2FsZW5kYXJfQXBpX0NvbmZpZ19Db25uZWN0b3JfTW9kZXJhdGlvbiI6Njp7czoxNzoiACoAY29ubmVjdG9yQ2xhc3MiO3M6MTA6Ik1vZGVyYXRpb24iO3M6NToiACoAaWQiO2k6LTE7czo4OiIAKgBsYWJlbCI7czoxOToiUmVzb3VyY2UgTW9kZXJhdGlvbiI7czoxMjoiACoAZXZlbnRUeXBlIjtzOjE1OiJSZXNvdXJjZUJvb2tpbmciO3M6MTQ6IgAqAGx1RXZlbnRUeXBlIjtzOjE6IjEiO3M6MTc6IgAqAGhhc1Blcm1pc3Npb25zIjtiOjA7fQ==
         */
        $newRecord->updateConfig($config);

        $mapper->save($newRecord);
    }
});