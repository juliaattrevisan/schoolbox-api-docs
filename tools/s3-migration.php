<?php

use Aura\Di;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Resource;
use Schoolbox\Resource\Storage;

$options = getopt("v");

require_once __DIR__ . '/../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel('S3 Storage Migration', [
    'verbose' => isset($options['v']) ? $options['v'] : null
]);

$kernel->run(function ($di, $loop, $log, $opts) {

    $line = str_repeat('-', 150);
    $log->info($line);

    // Grab local storage directory

    $storage_dir = \Config::get('storage_dir');

    // Retrieve all files from the database

    $storageMapper  = new Storage\Mapper();
    $files = $storageMapper->findAll();

    // Iterate files in database

    $count = 0;
    $countUploaded = 0;
    $countUnknown = 0;

    /** @var Schoolbox\Resource\Storage\Model $file */
    foreach ($files as $file)
    {
        $count++;

        // Make sure we using the S3 file adapter

        if ($file->getFilesystem()->getAdapter() instanceof \League\Flysystem\AwsS3v3\AwsS3Adapter)
        {
            if (!$file->exists())
            {
                // Check if the file exists

                $filepath = $storage_dir . "/" . $file->getPath();

                // File does not exist on S3, try to find it on local storage and save it to S3

                if (file_exists($filepath))
                {
                    // File was found locally, write it to S3

                    $log->info("Uploading:\t\t\t{$filepath} (" . floor(filesize($filepath)/1024) . " KB)");
                    $file->write(file_get_contents($filepath));
                    $countUploaded++;

                    // Delete local copy

                    unlink($filepath);

                    $log->info("Upload Completed");

                    // Recursively delete empty folders we might have created since deleting the file

                    $directoryArray = array_filter(explode("/", chunk_split(basename($filepath), 5, '/')));
                    $filepath = str_replace("/" . basename($filepath), "", $filepath);

                    for ($i = count($directoryArray)-1; $i >= 0; $i--)
                    {
                        try {
                            rmdir($filepath);
                        } catch (\Exception $e) {
                            // Directory might not be empty, just stop recursing up
                            break;
                        }

                        $filepath = str_replace("/" . $directoryArray[$i], "", $filepath);
                    }
                }
                else
                {
                    $log->info("Unknown File:\t\t\t{$file->getFilename()}");
                    $countUnknown++;
                }
            }
            else
            {
                $log->info("Skipping File:\t\t\t{$file->getFilename()}");
            }
        }
        else
        {
            $log->error("Error: Unexpected file adapter - " . get_class($file->getFilesystem()->getAdapter()));
            exit;
        }
    }

    $log->info($line);
    $log->info("Files In Storage Table:\t\t{$count}");
    $log->info("Files Orphaned:\t\t\t{$countUnknown}");
    $log->info("Files Uploaded:\t\t\t{$countUploaded}");
});
