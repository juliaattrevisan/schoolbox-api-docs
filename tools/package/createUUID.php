#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Ramsey\Uuid\Uuid;

require_once __DIR__ . '/../../conf/core/require.php';

const UUID_FIELD = 'uuid';

if ($argc !== 2) {
    echo "please parse in the source file\n";
    die();
}

function displayRow($row): void
{
    echo '"';
    echo implode('","', $row);
    echo "\"\n";
}

if (($handle = fopen($argv[1], "r")) !== false) {
    $firstRow = null;
    while (($row = fgetcsv($handle)) !== false) {
        if ($firstRow === null) {
            // check if we a uuid field?
            if (in_array(UUID_FIELD, $row)) {
                $firstRow = $row;
                displayRow($row);
                continue;
            }
            $row[] = UUID_FIELD;
            $firstRow = $row;
        }
        // if data doesn't exist in the uuid column, generate
        $uuid_column = array_search(UUID_FIELD, $firstRow);
        if (!($row[$uuid_column] ?? false) || ( 'NULL' === $row[$uuid_column])) {
            $row[$uuid_column] = (string)Uuid::uuid4();
        }
        displayRow($row);
    }
}
