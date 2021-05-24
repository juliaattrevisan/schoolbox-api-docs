#!/usr/bin/php
<?php

/**
 * Repair Google Drive file extensions that were mistakenly cast to .pdf
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App
{

    /**
     * Update Query for fixing the busted records
     */
    private const UPDATE_QUERY = '
        UPDATE `storage` SET filename = :filename, ext = :ext
        WHERE storage_id = :storageId
    ';


    /**
     * Re-assign originally intended file extension
     *
     */
    public function run(): void
    {
        $updateStorage = $this->getDatabaseInternal()->prepareQuery(self::UPDATE_QUERY);

        /* Always run this file, because it's in the updates folder
         */
        $this->getLogger()->warning('Running in Perform mode');

        /* Get all the storage records with `pdf` as their extensions
         * These are the files that might have been affected by SA-8297
         *
         * For each of the files we found, find the file extension in the `filename` column
         * This is what the file originally was
         *
         * Check that this extension is one of the allowedFiles extensions
         * If it wasn't in that table, it might not be a file extension at all
         *
         * This is one way to differentiate broken SA-8297 files from pdfs that originally
         * had a full stop in their file names
         */
        $query = "
            SELECT 
                storage_id, filename, ext,
                SUBSTRING_INDEX(filename, '.', -1) AS original_extension
            FROM `storage`
            INNER JOIN allowedFileExtension afe ON SUBSTRING_INDEX(filename, '.', -1) = afe.extension
            WHERE `ext` = 'pdf'
            AND filename LIKE '%.%'
        ";

        /* If it was a valid allowedFiles extension in the `filename` column, and it has
         * a `pdf` extension, then assume it needs correction
         */
        $statement = $this->getDatabaseInternal()->executeQuery($query);

        $this->getDatabaseInternal()->beginTransaction();

        $count = 0;

        foreach ($statement->getIterator() as $row) {
            $newFilename = substr($row['filename'], 0, \strrpos($row['filename'], '.'));

            $updateStorage->execute([
                'filename' => $newFilename,
                'ext' => $row['original_extension'],
                'storageId' => $row['storage_id']
            ]);

            $this->getLogger()->notice(sprintf(
                'storageId %d: rename (%s, %s) to (%s, %s)',
                $row['storage_id'],
                $row['filename'],
                $row['ext'],
                $newFilename,
                $row['original_extension']
            ));

            $count++;
        }

        $this->getDatabaseInternal()->commit();

        /* "Helpful" count
         */
        $this->getLogger()->notice(sprintf('Updated %d records.', $count));
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
