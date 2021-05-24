#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
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
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeGroupMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Application\Daemon;

require __DIR__ . '/../../../conf/core/require.php';

$kernel = new Schoolbox\Application\Daemon\Kernel(
    "'Insert Over All Grade config'",
    [
        Daemon\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

$app = new class extends App {

    /** @var LoggerInterface */
    private $log;

    /** @var IConnector */
    private $database;

    /** @var IStatement */
    private $insertStatement;

    /** @var IStatement */
    private $sequenceStatement;

    /**
     * @inheritdoc
     * @throws PDOException
     */
    public function run(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags): void
    {
        $this->log      = $log;
        $this->database = $di->get('databaseInternal');

        $defaultGradeGroupQuery = $this->database->executeQuery('SELECT value FROM config WHERE name="default_grade_group"');
        $defaultGradeGroup      = $defaultGradeGroupQuery->fetchColumn();

        $sequenceQuery = $this->database->executeQuery('SELECT sequence+1 FROM config WHERE name="show_overall_grade"');
        $sequence      = $sequenceQuery->fetchColumn();

        $this->prepareStatements();
        $this->database->beginTransaction();
        try {
            $this->execute($defaultGradeGroup, $sequence);
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
        $this->insertStatement = $this->database->prepareQuery('
             INSERT INTO config ( name,  value,  title,  description,  field_id,  options,  type_id,  editable,  hidden,  required,  module,  sequence)
             VALUES             (:name, :value, :title, :description, :field_id, :options, :type_id, :editable, :hidden, :required, :module, :sequence)
        ');

        $this->sequenceStatement = $this->database->prepareQuery('
             UPDATE config SET sequence = sequence+1 WHERE module = :module AND type_id = :type_id AND sequence >= :sequence
        ');
    }

    /**
     * Executes the app
     * @param string $defaultGradeGroup
     * @param string $sequence
     * @throws \Alaress\Database\Exception
     */
    private function execute(string $defaultGradeGroup, string $sequence): void
    {
        // Shift sequence of existing settings
        $this->sequenceStatement->execute([
            'type_id'  => ConfigMapper::TYPE_LMS_POLICY,
            'module'   => ConfigMapper::MODULE_SETTINGS,
            'sequence' => $sequence,
        ]);

        // Insert the new config
        $this->insertStatement->execute([
            'name'        => 'overall_grade',
            'value'       => $defaultGradeGroup,
            'title'       => 'Display Student Overall Grade',
            'description' => 'Controls the default method for displaying the students overall grade on the grade report. This value may be overridden by the year level configuration.',
            'field_id'    => ConfigMapper::FIELD_SELECT_LOOKUP,
            'options'     => serialize([
                    'mapper'                                      => GradeGroupMapper::class,
                    SelectLookup\Renderer::OPTION_KEY_FIND_METHOD => 'findAllForAdminOption',
                ]),
            'type_id'     => ConfigMapper::TYPE_LMS_POLICY,
            'editable'    => 'true',
            'hidden'      => 'false',
            'required'    => 'true',
            'module'      => ConfigMapper::MODULE_SETTINGS,
            'sequence'    => $sequence,
        ]);

        // Update existing default grade group, which will no longer be used for markbooks
        $this->database->executeQuery('UPDATE config SET title = "Default Grade Group", description = "Default grading scheme for Letter Grade Mark Type" WHERE name = "default_grade_group"');
    }
};

$kernel->run($app);
