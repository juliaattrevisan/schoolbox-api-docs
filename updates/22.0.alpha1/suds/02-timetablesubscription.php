<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {

    /* Create a new SUDS Timetable calendar subscription
     */
    public function run(): void
    {
        $config = (new \Calendar_Api_Config_Connector_SudsTimetable())
            ->setLabel('Timetable Events')
            ->setEventType('SudsTimetable') // This is actually the classname corresponding to Calendar/Api/Config/Connector/SudsTimetable.php
            ->setLuEventType('1');

        $calendarApiConfigMapper = \Calendar_Api_Config_Mapper::getInstance();

        $newConfig = base64_encode(serialize($config));
        $statement = $calendarApiConfigMapper->getDatabase()->query("
            INSERT INTO calendar_connector (event_type, connector_class, config, status) VALUES ('SudsTimetable', 'Schoolbox', '{$newConfig}', 0);
        ");
        $statement->execute();
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
