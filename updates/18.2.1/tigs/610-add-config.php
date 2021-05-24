#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\IStatement;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;
use Custom\Tigs;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Application\Daemon;

require __DIR__ . '/../../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    "'Insert TIGS config'",
    [
        Daemon\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

$app = new class extends App
{

    /** @var LoggerInterface */
    private $log;

    /** @var IConnector */
    private $database;

    /** @var IStatement */
    private $insertStatement;


    /**
     * @inheritdoc
     * @throws PDOException
     * @throws Di\Exception\ServiceNotFound
     */
    public function run(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags): void
    {
        $this->log      = $log;
        $this->database = $di->get('databaseInternal');

        $this->prepareStatements();
        $this->database->beginTransaction();
        try {
            $this->execute();
            $this->database->commit();
        } catch (\Alaress\Database\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
            $this->database->rollBack();
        }
    }


    /**
     * Prepare statements that will be re-used to avoid re-compile time
     */
    private function prepareStatements(): void
    {
        $this->insertStatement = $this->database->prepareQuery("
             INSERT INTO config ( name,  value,  title,  description,  field_id,  options,  editable,  hidden,  required,  module)
             VALUES             (:name, :value, :title, :description, :field_id, :options, :editable, :hidden, :required, :module)
        ");
    }


    /**
     * Executes the app
     *
     * @throws \Alaress\Database\Exception
     */
    private function execute(): void
    {
        $gradeConfigs = [
            ['name' => Tigs\Summative\Config::_TIGS_GRADES_INFO_YEAR_7_10, 'title' => 'Year 7-10'],
            ['name' => Tigs\Summative\Config::_TIGS_GRADES_INFO_YEAR_11_IB, 'title' => 'Year 11 IB'],
            ['name' => Tigs\Summative\Config::_TIGS_GRADES_INFO_YEAR_11_HSC, 'title' => 'Year 11 HSC'],
            ['name' => Tigs\Summative\Config::_TIGS_GRADES_INFO_YEAR_12_IB, 'title' => 'Year 12 IB'],
            ['name' => Tigs\Summative\Config::_TIGS_GRADES_INFO_YEAR_12_HSC, 'title' => 'Year 12 HSC'],
        ];

        foreach ($gradeConfigs as $config) {
            $this->insertConfig(
                $config['name'],
                '',
                "Explanation of grades for Curriculum {$config['title']}",
                "Displayed on students' Participation Reports to inform parents of grade derivation",
                ConfigMapper::FIELD_HTML,
                null,
                true,
                false,
                false,
                ConfigMapper::MODULE_CUSTOM
            );
        }
    }


    /**
     * Insert a config into the config table
     *
     * @param string      $name
     * @param string|null $value
     * @param string      $title
     * @param string      $description
     * @param int         $fieldId
     * @param string|null $options
     * @param bool        $editable
     * @param bool        $hidden
     * @param bool        $required
     * @param string      $module
     * @return bool
     * @throws \Alaress\Database\Exception
     */
    private function insertConfig(
        string $name,
        ?string $value,
        string $title,
        string $description,
        int $fieldId,
        ?string $options,
        bool $editable,
        bool $hidden,
        bool $required,
        string $module
    ): bool {
        $this->log->info("Inserting config. name: '{$name}', value: '{$value}', title: '{$title}'");

        return $this->insertStatement->execute([
            'name'        => $name,
            'value'       => $value,
            'title'       => $title,
            'description' => $description,
            'field_id'    => $fieldId,
            'options'     => $options,
            'editable'    => $editable ? 'true' : 'false',
            'hidden'      => $hidden ? 'true' : 'false',
            'required'    => $required ? 'true' : 'false',
            'module'      => $module,
        ]);
    }
};

try {
    $kernel->run($app);
} catch (Throwable $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
