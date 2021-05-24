<?php

/**
 * Repair obsolete Timetable_ExternalConstrained configurations
 *
 * The serialized configuration sometimes points to a nonexistent class Timetable_External
 * This was replaced by Timetable_ExternalConstrained
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Application\Daemon;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Update Timetable_External to Timetable_ExternalConstrained',
    [
        Daemon\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {
    $db = Database::getInstance();

    /* Assume multiple instances of Timetable_ExternalConstrained
     * This is a pretty awful scenario which typically never exists, but might, if people are tinkering
     */
    $existing = $db->query("
        SELECT id, config 
        FROM calendar_connector
        WHERE event_type = 'Timetable'
        AND connector_class = 'ExternalConstrained'
    ");

    while ($row = $existing->fetch()) {
        $newRecord = new Calendar_Api_Config_Record();
        $newRecord->setConfig($row['config']);

        /* Figure out whether it was referencing an obsolete class by popping open the serialized object
         * and checking its settings - use an instance to gain access to decode()/encode() methods which
         * make things easier
         */
        $configuration = $newRecord->decodeConfig();

        /* Timetable_External no longer exists; it's now Timetable_ExternalConstrained
         * Update it, encode it, save it again
         */
        if (($configuration->getConnectorClass() == 'External') && ($configuration->getEventType() == 'Timetable')) {
            /* Some outdated records may still be of type Calendar_Api_Config_Connector_External
             * This needs to be updated as well
             */
            $updatedConfiguration = new Calendar_Api_Config_Connector_ExternalConstrained();
            $updatedConfiguration->setConnectorClass('ExternalConstrained');
            $updatedConfiguration->setLabel($configuration->getLabel());
            $updatedConfiguration->setEventType($configuration->getEventType());
            $updatedConfiguration->setLuEventType($configuration->getLuEventType());

            $newRecord->updateConfig($updatedConfiguration);

            $statement = $db->prepare('
                UPDATE calendar_connector
                SET config = :config
                WHERE id = :id
            ');

            $statement->execute([
                'id' => $row['id'],
                'config' => $newRecord->getConfig(),
            ]);
        }
    }

    /* Now blow away any old configurations that are for Timetable_External
     *
     * These have no usage anymore; even if they were configured, there is nothing to retrieve the events
     * (Calendar_EventType_Timetable_External is deleted)
     */
    $db->query("
        DELETE FROM calendar_connector 
        WHERE event_type = 'Timetable' 
        AND connector_class = 'External'
    ");
});
