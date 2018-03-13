#!/usr/bin/php
<?php
/**
 * Script to migrate existing thumbnails, posters and transcodes across to OTFS storage
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   2017 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

use Aura\Di\Container as Injector;
use Aura\Di\Exception\ServiceNotFound as ServiceNotFound;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

require_once __DIR__ . '/../../conf/core/require.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Media Migrator', [
    'verbose' => [false, false, false],
]);

try {
    $kernel->run(function ($di, $loop, $log, $opts) {
        /**
         * @var Injector        $di
         * @var LoopInterface   $loop
         * @var LoggerInterface $log
         * @var array           $opts
         */

        // ------------------------------------------------------------------------------------------------------------
        // Get mappers
        // ------------------------------------------------------------------------------------------------------------

        try {
            /** @var IConfigLoader $configLoader */
            $configLoader = $di->get('config');

            /** @var MapperFactory $mapperFactory */
            $mapperFactory = $di->get('mapperFactory');
        } catch (ServiceNotFound $e) {
            $log->critical("Unknown service requested");
            exit;
        }

        /** @var ConfigMapper $configMapper */
        $configMapper = $mapperFactory->get(ConfigMapper::class);

        /** @var StorageMapper $storageMapper */
        $storageMapper = $mapperFactory->get(StorageMapper::class);

        // ------------------------------------------------------------------------------------------------------------
        // Let kernel decide how to handle dead child processes
        // ------------------------------------------------------------------------------------------------------------

        pcntl_signal(SIGCHLD, SIG_DFL);

        // ------------------------------------------------------------------------------------------------------------
        // Allow only a single migration at a time
        // ------------------------------------------------------------------------------------------------------------

        $lock = new Alaress_Lock($configLoader->get('video_dir') . '/.migration.lock');

        if (!$lock->acquire()) {
            $log->warning("Media migration already running, checking timeout...");
            exit;
        }

        // ------------------------------------------------------------------------------------------------------------
        // Get transcoded files from local storage that we need to move
        // ------------------------------------------------------------------------------------------------------------

        $globVideos = "{$configMapper->get("video_dir")}/*.mp4";
        $log->info("Scanning '{$globVideos}'...");
        $videos = glob($globVideos);

        $globAudios = "{$configMapper->get("video_dir")}/*.mp3";
        $log->info("Scanning '{$globAudios}'...");
        $audios = glob($globAudios);

        $globPosters = "{$configMapper->get("video_dir")}/*-poster.jpg";
        $log->info("Scanning '{$globPosters}'...");
        $posters = glob($globPosters);

        $globThumbnails = "{$configMapper->get("video_dir")}/*-64.jpg";
        $log->info("Scanning '{$globThumbnails}/*-64.jpg'...");
        $thumbnails = glob($globThumbnails);

        $log->info("");

        // Tally up the booty

        $countVideos     = count($videos);
        $countAudios     = count($audios);
        $countPosters    = count($posters);
        $countThumbnails = count($thumbnails);

        $countTotal = $countVideos + $countAudios + $countPosters + $countThumbnails;

        // Shortcut if nothing left to migrate

        if ($countTotal == 0) {
            $log->info("Nothing to migrate");
            $lock->release();
            exit;
        }

        // Track results of migration

        $failed  = 0;
        $success = 0;

        $log->info("Found {$countVideos} videos, {$countAudios} audio, {$countPosters} posters, {$countThumbnails} thumbnails");
        $log->info("");

        // Obtain the destination
        $storageDir = $configMapper->get("storage_dir");

        // ------------------------------------------------------------------------------------------------------------
        // Migrate videos
        // ------------------------------------------------------------------------------------------------------------

        // Videos need to be migrated first so that the thumbnails/posters have something to be associated with

        $log->info("Processing videos...");
        $results = uploadArrayToStorage($log, $videos, $storageMapper, $storageDir, 'video');

        $success += $results["success"];
        $failed  += $results["failed"];

        // ------------------------------------------------------------------------------------------------------------
        // Migrate audio
        // ------------------------------------------------------------------------------------------------------------

        // Videos need to be migrated first so that the thumbnails/posters have something to be associated with

        $log->info("Processing audio...");
        $results = uploadArrayToStorage($log, $audios, $storageMapper, $storageDir, 'audio');

        $success += $results["success"];
        $failed  += $results["failed"];

        // ------------------------------------------------------------------------------------------------------------
        // Migrate posters
        // ------------------------------------------------------------------------------------------------------------

        $log->info("Processing posters...");
        $results = uploadArrayToStorage($log, $posters, $storageMapper, $storageDir, 'poster');

        $success += $results["success"];
        $failed  += $results["failed"];

        // ------------------------------------------------------------------------------------------------------------
        // Migrate thumbnails
        // ------------------------------------------------------------------------------------------------------------

        $log->info("Processing thumbnails...");
        $results = uploadArrayToStorage($log, $thumbnails, $storageMapper, $storageDir, 'thumbnail');

        $success += $results["success"];
        $failed  += $results["failed"];

        // ------------------------------------------------------------------------------------------------------------
        // Display results
        // ------------------------------------------------------------------------------------------------------------

        if ($failed > 0) {
            $log->info("Processing completed with errors: {$success} succeeded, {$failed} failed");
        } else {
            $log->info("Processing completed: {$success} media items processed successfully");
        }

        // ------------------------------------------------------------------------------------------------------------
        // Release lock
        // ------------------------------------------------------------------------------------------------------------

        $lock->release();
    });
} catch (Exception $e) {
    // this is purely to stop Storm from covering the entire closure in yellow
    throw $e;
}

// --------------------------------------------------------------------------------------------------------------------
// Helper functions
// --------------------------------------------------------------------------------------------------------------------

/**
 * Iterate over the array of files and migrate appropriate
 *
 * @param LoggerInterface $log
 * @param array           $filenames
 * @param StorageMapper   $storageMapper
 * @param string          $storageDir
 * @param string          $type
 *
 * @return array
 */
function uploadArrayToStorage(LoggerInterface $log, array $filenames, StorageMapper $storageMapper, $storageDir, $type)
{
    $log->info("");

    $success      = 0;
    $failed       = 0;
    $countCurrent = 1;
    $countQueue   = count($filenames);

    /** @var string $filename */
    foreach ($filenames as $filename) {
        $log->info("    - Migrating:              {$countCurrent}/{$countQueue}");
        $log->info("    - Migration Type:         " . ucfirst($type));
        $log->info("    - Source File:            {$filename}");

        if (uploadToStorage($log, $filename, $storageMapper, $storageDir, $type)) {
            $success++;
        } else {
            $log->error("    - Failed:                 {$filename}");
            $failed++;
        }

        $log->info("");

        $countCurrent++;
    }

    return [
        'success' => $success,
        'failed'  => $failed,
    ];
}

/**
 * Upload a single thumbnail to storage and amend corresponding video record
 *
 * @param LoggerInterface $log
 * @param string          $filename
 * @param StorageMapper   $storageMapper
 * @param string          $storageDir
 * @param string          $type
 *
 * @return bool
 */
function uploadToStorage(LoggerInterface $log, $filename, StorageMapper $storageMapper, $storageDir, $type)
{
    // ----------------------------------------------------------------------------------------------------------------
    // Retrieve existing video storage object
    // ----------------------------------------------------------------------------------------------------------------

    // Extract the hash from the filename

    try {
        if ($type == 'video') {
            $filenameArray = explode(".mp4", $filename);
        } elseif ($type == 'audio') {
            $filenameArray = explode(".mp3", $filename);
        } else {
            $filenameArray = explode("-", $filename);
        }

        $hash = end(explode("/", $filenameArray[0]));

        $videoStorage = $storageMapper->findByHash($hash);

        $log->info("    - Storage Filename:       {$filename}");
    } catch (MapperException $e) {
        $log->error("    - Failed:                 Unable to locate corresponding storage object");

        return false;
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Upload the file to storage
    // ----------------------------------------------------------------------------------------------------------------

    $suffix = $type == 'video' || $type == 'audio' ? 'transcode' : $type;
    $filenameImage = "{$storageDir}/{$videoStorage->getPath()}-{$suffix}";
    $log->info("    - Filename:      {$filename}");
    $log->info("    - Uploaded Filename:      {$filenameImage}");
    rename($filename, $filenameImage);



    return true;
}