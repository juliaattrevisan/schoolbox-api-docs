#!/usr/bin/php
<?php

/**
 * Update sequencing of pastoral types and subtypes.
 * * Types are ordered by name
 * * Subtypes are ordered by name within their type
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
        $db                  = $this->getDatabaseInternal();

        $typeSequence        = 1;
        $selectTypesQuery    = $db->prepareQuery("SELECT id FROM lu_pastoral_type ORDER BY name");
        $updateTypeQuery     = $db->prepareQuery("UPDATE lu_pastoral_type SET sequence=:sequence WHERE id=:typeId");

        $subtypeSequence     = 1;
        $selectSubtypesQuery = $db->prepareQuery("SELECT id FROM lu_pastoral_subtype WHERE pastoral_type_id=:typeId ORDER BY name");
        $updateSubtypeQuery  = $db->prepareQuery("UPDATE lu_pastoral_subtype SET sequence=:sequence WHERE id=:subtypeId");

        // Update each type's sequence
        $selectTypesQuery->execute();
        foreach ($selectTypesQuery->fetchColumnArray('id') as $typeId) {
            $updateTypeQuery->execute(['typeId' => $typeId, 'sequence' => $typeSequence++]);

            // Update each subtype's sequence
            $selectSubtypesQuery->execute(['typeId' => $typeId]);
            foreach ($selectSubtypesQuery->fetchColumnArray('id') as $subtypeId) {
                $updateSubtypeQuery->execute(['subtypeId' => $subtypeId, 'sequence' => $subtypeSequence++]);
            }
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
