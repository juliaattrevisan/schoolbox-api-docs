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
use Schoolbox\Admin\Settings\FieldType\SelectLookup;
use Schoolbox\Application\Daemon\App;
use Custom\Tigs;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Application\Daemon;

require __DIR__ . '/../../../conf/core/require.php';

$kernel = new Schoolbox\Application\Daemon\Kernel(
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
        $this->insertConfig(
            Tigs\Summative\Config::_TIGS_WORK_TYPE_ACADEMIC_ACHIEVEMENT_MATRIX,
            '',
            'Work Type - Academic Achievement Matrix',
            "Rubrics attached to assessments of this work-type will appear on a student's Subject Report in the Academic Achievement Matrix component",
            ConfigMapper::FIELD_SELECT_LOOKUP,
            \serialize([SelectLookup\Renderer::OPTION_KEY_MAPPER => WorkType\Mapper::class,]),
            true,
            false,
            true,
            ConfigMapper::MODULE_CUSTOM
        );

        $this->insertConfig(
            Tigs\Summative\Config::_TIGS_WORK_TYPE_ACADEMIC_ACHIEVEMENT_SUMMARY,
            '',
            'Work Type - Academic Achievement Summary',
            "Rubrics attached to assessments of this work-type will be used to derive the values displayed in the Academic Achievement Summary table on a student's Subject Report",
            ConfigMapper::FIELD_SELECT_LOOKUP,
            \serialize([SelectLookup\Renderer::OPTION_KEY_MAPPER => WorkType\Mapper::class,]),
            true,
            false,
            true,
            ConfigMapper::MODULE_CUSTOM
        );

        $this->insertConfig(
            Tigs\Summative\Config::_TIGS_WORK_TYPE_NESA_SCORE,
            '',
            'Work Type - NESA Score',
            "Marks given to assessments of this work type will populate the 'NESA Score' graph",
            ConfigMapper::FIELD_SELECT_LOOKUP,
            \serialize([SelectLookup\Renderer::OPTION_KEY_MAPPER => WorkType\Mapper::class,]),
            true,
            false,
            true,
            ConfigMapper::MODULE_CUSTOM
        );

        $this->insertConfig(
            Tigs\Summative\Config::_TIGS_WORK_TYPE_MYP_FINAL_ACHIEVEMENT_LEVELS,
            '',
            'Work Type - MYP Final Achievement Levels',
            "Marks given to the rubric attached to Due Works with this work-type will populate the student's 'MYP - Final Achievement Levels' table and 'MYP Grade' graph",
            ConfigMapper::FIELD_SELECT_LOOKUP,
            \serialize([SelectLookup\Renderer::OPTION_KEY_MAPPER => WorkType\Mapper::class,]),
            true,
            false,
            true,
            ConfigMapper::MODULE_CUSTOM
        );

        $this->insertConfig(
            Tigs\Summative\Config::_TIGS_WORK_TYPE_DIPLOMA_PROGRAMME_REPORT_GRADE,
            '',
            'Work Type - DP Report Grade',
            "Expected to be assigned to due works that uses the 'Total Mark' mark scheme. Marks given in this assessment will displayed on students' DP Grade graph",
            ConfigMapper::FIELD_SELECT_LOOKUP,
            \serialize([SelectLookup\Renderer::OPTION_KEY_MAPPER => WorkType\Mapper::class,]),
            true,
            false,
            true,
            ConfigMapper::MODULE_CUSTOM
        );
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
