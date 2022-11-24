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
            ->deactivate();

        $encodedConfig = encodeConfigUsingOldMethod($record->getConfig());

        $query = "
            INSERT INTO calendar_connector (event_type, connector_class, config, status)
            VALUES ('Fixtures', 'Schoolbox', :encodedConfig, 0)
        ";

        $this->getDatabaseInternal()->query($query, ['encodedConfig' => $encodedConfig]);
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();

// Functions. //////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Prior to SA-14746 (released in version 23.0.alpha1), this method was used to encode calendar subscriptions.
 * The old encoding method has been reproduced here so as to preserve the old behaviour in update scripts for
 * versions <23.0.alpha1.
 *
 * @param string $config
 * @return string
 */
function encodeConfigUsingOldMethod(string $config): string
{
    return base64_encode(serialize($config));
}
