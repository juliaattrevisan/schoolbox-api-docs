#!/usr/bin/php
<?php

/**
 * Sequence document classifications by name.
 *
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {
    public function run(): void
    {
        $db          = $this->getDatabaseInternal();

        $sequence    = 1;
        $selectQuery = $db->prepareQuery("SELECT id FROM lu_pastoral_document_classification ORDER BY name");
        $updateQuery = $db->prepareQuery("UPDATE lu_pastoral_document_classification SET sequence=:sequence WHERE id=:id");

        // Update each type's sequence
        $selectQuery->execute();
        foreach ($selectQuery->fetchColumnArray('id') as $id) {
            $updateQuery->execute(['id' => $id, 'sequence' => $sequence++]);
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
