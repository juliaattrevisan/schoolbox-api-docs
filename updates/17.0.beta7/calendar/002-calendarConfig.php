<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 */
use Psr\Log\LoggerInterface;


$opts = require  __DIR__ . '/../../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Routing', [
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'errors'  => E_ALL,
    'fields'  => array_keys($opts),
]);

$kernel->run(function($di, $loop, LoggerInterface $log) {

    $calConfigMapper = Calendar_Api_Config_Mapper::getInstance();

    $configs = $calConfigMapper->findByColumnList(
        [
            'event_type'      => 'Timetable',
            'connector_class' => 'ExternalConstrained',
            'status'          => 1
        ]
    );

    foreach ($configs as $currentConfig) {
        try {
            /* @var $currentConfig Calendar_Api_Config_Record */
            $config = $currentConfig->decodeConfig();

            if($config instanceof Calendar_Api_Config_Connector_External) {

                $log->info('Calendar Timetable config found, converting to ExternalConstrained');

                $configNew = new Calendar_Api_Config_Connector_ExternalConstrained();
                $configNew->setDuration(2);
                $configNew->setId($config->getId());
                $configNew->setLabel($config->getLabel());
                $configNew->setConnectorClass('ExternalConstrained');
                $configNew->setEventType($config->getEventType());
                $configNew->setLuEventType($config->getLuEventType());

                $currentConfig->updateConfig($configNew);
                $calConfigMapper->save($currentConfig);
            }


        } catch (DomainException $e) {
            //config does not exist or is disabled
        }
    }
});



