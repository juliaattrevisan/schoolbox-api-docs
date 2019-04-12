#!/usr/bin/php
<?php
/**
 * Repair Google Drive file extensions that were mistakenly cast to .pdf
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;

$opts = require __DIR__ . '/../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel("'Repair Google Drive PDF Assignations'", [
    'verbose' => $opts['v'] ?? null,
    'errors'  => E_ALL,
]);

$app = new class extends App
{

    /**
     * Update Query for fixing the busted records
     */
    private const UPDATE_QUERY = '
        UPDATE `storage` SET filename = :filename, ext = :ext
        WHERE storage_id = :storageId
    ';


    /**
     * @var IConnector
     */
    private $database;

    /**
     * Re-assign originally intended file extension
     *
     * @param Di\Container    $di
     * @param LoopInterface   $loop
     * @param LoggerInterface $log
     * @param array           $opts
     */
    public function run(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts): void
    {
        $this->database = $di->get('databaseInternal');

        $updateStorage = $this->database->prepareQuery(self::UPDATE_QUERY);

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
        $statement = $this->database->executeQuery($query);

        foreach ($statement->getIterator() as $row) {
            $updateStorage->execute([
                'filename' => substr($row['filename'], 0, \strrpos($row['filename'], '.')),
                'ext' => $row['original_extension'],
                'storageId' => $row['storage_id']
            ]);
        }
    }
};

try {
    $kernel->run($app);
} catch (Throwable $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
