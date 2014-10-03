<?php
/**
 * Update for adding default labels to all calendar event configs.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../../include/Bootstrap.php';

/** @var Calendar_Api_Config_Record[] $configs */
$mapper  = Calendar_Api_Config_Mapper::getInstance();
$configs = $mapper->findAll();
foreach ($configs as $config) {
    $internalConfig = $config->decodeConfig();
    $internalConfig->setLabel($internalConfig->getDefaultLabel());
    $config->updateConfig($internalConfig);
}
$mapper->save($configs);
