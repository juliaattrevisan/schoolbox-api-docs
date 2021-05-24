#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Alaress\Database;
use Schoolbox\Config\ConfigMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Converts config from textarea to input, and attempts to preserve config
 */
$app = new class extends CliTool\App
{
    /**
     * Hands off execution over to the App
     */
    public function run(): void
    {
        $db = $this->getDatabaseInternal();
        $db->beginTransaction();
        try {
            $this->execute();
            $db->commit();
        } catch (Database\Exception $e) {
            $this->getLogger()->error($e->getMessage());
            $this->getLogger()->error($e->getTraceAsString());
            $this->getLogger()->error('Rolling back transaction');
            $db->rollBack();
        }
    }


    /**
     * Executes the high-level logic
     */
    private function execute(): void
    {
        $configNames = [
            \Custom\Tigs\Summative\Config::_TIGS_GRADES_INFO_YEAR_7_10,
            \Custom\Tigs\Summative\Config::_TIGS_GRADES_INFO_YEAR_11_IB,
            \Custom\Tigs\Summative\Config::_TIGS_GRADES_INFO_YEAR_11_HSC,
            \Custom\Tigs\Summative\Config::_TIGS_GRADES_INFO_YEAR_12_IB,
            \Custom\Tigs\Summative\Config::_TIGS_GRADES_INFO_YEAR_12_HSC,
        ];

        foreach ($configNames as $configName) {
            $oldValue   = $this->getConfigValue($configName);
            $homepageId = $this->parseHomepageId($oldValue);
            $this->updateConfig($configName, $homepageId);
        }
    }


    /**
     * Returns the value of the specified config
     *
     * @param string $configName
     * @return string|null
     * @throws Database\Exception
     */
    private function getConfigValue(string $configName): ?string
    {
        $sql = "SELECT value FROM config WHERE name = :name";

        return $this->getDatabaseInternal()->executeQuery($sql, ['name' => $configName])->fetchColumn();
    }


    /**
     * Attempts to find and return a homepage id from the old value
     *
     * @param string|null $oldValue
     * @return string|null
     */
    private function parseHomepageId(?string $oldValue): ?string
    {
        if (!$oldValue) {
            return null;
        }
        if (preg_match('#/homepage/(\d+)#', $oldValue, $matches)) {
            return $matches[1];
        }
        return null;
    }


    /**
     * Updates the specified config's fieldType and value
     *
     * @param string      $configName
     * @param string|null $homepageId
     * @throws Database\Exception
     */
    private function updateConfig(string $configName, ?string $homepageId): void
    {
        $this->getLogger()->info("Setting '$configName' to 'input' field with value '$homepageId'");

        $sql = "
            UPDATE config
            SET 
                title = CONCAT('Homepage id of ', title),
                value = :value,
                field_id = :field_id
            WHERE name = :name
        ";

        $this->getDatabaseInternal()->executeQuery($sql, [
            'field_id' => ConfigMapper::FIELD_INPUT,
            'value'    => $homepageId,
            'name'     => $configName,
        ]);
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
