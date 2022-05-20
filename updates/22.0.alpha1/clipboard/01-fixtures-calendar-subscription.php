<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {

    /* Create a new Fixtures calendar subscription
     */
    public function run(): void
    {
        $config = (new \Calendar_Api_Config_Connector_Fixtures())
            ->setLabel('Fixtures')
            ->setEventType('Fixtures') // This is actually the classname corresponding to Calendar/Api/Config/Connector/Fixtures.php
            ->setLuEventType('1');

        $calendarApiConfigMapper = \Calendar_Api_Config_Mapper::getInstance();

        $record = $calendarApiConfigMapper
            ->createModel()
            ->setEventType('Fixtures') // This is actually the classname corresponding to Calendar/Api/Config/Connector/Fixtures.php
            ->setConnectorClass('Schoolbox')
            ->deactivate()
            ->encodeConfig($config);

        $query = "
            INSERT INTO calendar_connector (event_type, connector_class, config, status)
            VALUES ('Fixtures', 'Schoolbox', '{$record->getConfig()}', 0)
        ";

        $this->getDatabaseInternal()->query($query);
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
