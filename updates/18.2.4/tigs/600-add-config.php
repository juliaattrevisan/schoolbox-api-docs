#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Custom\Tigs;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\InsertHelper;

$opts = require __DIR__ . '/../../../conf/core/require-cli.php';

// TODO - START - Delete this once SA-8248 is merged. It will be handled by a single core require.php
require_once __DIR__ . '/../../../www/conf/conf.php';
require_once __DIR__ . '/../../../www/include/stdfuncs.php';
require_once __DIR__ . '/../../../library/constants.php';
set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../../../library/Autoloader.php';
Autoloader::initialise();
// TODO - END

$app = new class extends InsertHelper
{

    /**
     * @inheritDoc
     */
    protected function getInsertStatementSql(): string
    {
        return "
             INSERT INTO config ( name,  value,  title,  description,  field_id,  options,  editable,  hidden,  required,  module)
             VALUES             (:name, :value, :title, :description, :field_id, :options, :editable, :hidden, :required, :module)
        ";
    }


    /**
     * @inheritDoc
     */
    protected function insertConfigItems(): void
    {
        $this->insertRelativeDateConfig(1, Tigs\Summative\Config::_TIGS_START_TERM_1, '31 January');
        $this->insertRelativeDateConfig(2, Tigs\Summative\Config::_TIGS_START_TERM_2, '29 April');
        $this->insertRelativeDateConfig(3, Tigs\Summative\Config::_TIGS_START_TERM_3, '22 July');
        $this->insertRelativeDateConfig(4, Tigs\Summative\Config::_TIGS_START_TERM_4, '14 October');
    }


    /**
     * Inserts a relative data field for the specified term
     *
     * @param int    $term
     * @param string $name
     * @param string $value
     */
    private function insertRelativeDateConfig(int $term, string $name, string $value): void
    {
        $this->insertConfig(
            $name,
            $value,
            "Term $term Start Date",
            "Term $term is expected to start on this date. Expects a day and month, no year",
            ConfigMapper::FIELD_INPUT,
            null,
            true,
            false,
            false,
            ConfigMapper::MODULE_CUSTOM
        );
    }


    /**
     * Insert a config item into the config table
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
     */
    public function insertConfig(
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
    ): void {
        $this->getLogger()->notice("Inserting config. name: '{$name}', value: '{$value}', title: '{$title}'");

        $this->insertConfigItem([
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

$options = [CliTool\Kernel::OPTION_LEVEL_MIN => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

try {
    (new Schoolbox\Application\CliTool\Kernel($app, false, $options))->runApp();
} catch (Throwable $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
