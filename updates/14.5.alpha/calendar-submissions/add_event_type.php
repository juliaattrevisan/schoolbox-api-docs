<?php
/**
 * Update for setting submission event type to its current hardcoded value (4).
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../../include/Bootstrap.php';

/** @var Calendar_Api_Config_Record[] $configs */
$mapper  = Calendar_Api_Config_Mapper::getInstance();
$configs = $mapper->findByColumnList(
    array(
        'event_type'      => 'Submission',
        'connector_class' => 'Schoolbox',
    )
);
foreach ($configs as $config) {
    $internalConfig = $config->decodeConfig();
    $internalConfig->setLuEventType(4);
    $config->updateConfig($internalConfig);
}
$mapper->save($configs);
