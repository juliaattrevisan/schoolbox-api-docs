<?php

use Aura\Di;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Resource;
use Schoolbox\Resource\Storage;

require_once __DIR__ . '/../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel('S3 Storage Migration', []);

$kernel->run(function ($di, $loop, $log, $opts) {

    echo "\n----------------------------------------------------------------------------------------------------------------------------------------------------------------\nAmazon S3 Migration\n----------------------------------------------------------------------------------------------------------------------------------------------------------------\n\n";

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

                    echo "Uploading:\t\t\t{$filepath} (" . floor(filesize($filepath)/1024) . " KB)... ";
                    $file->write(file_get_contents($filepath));
                    $countUploaded++;

                    // Delete local copy

                    unlink($filepath);

                    echo "DONE\n";

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
                    echo "Unknown File:\t\t\t{$file->getFilename()}\n";
                    $countUnknown++;
                }
            }
            else
            {
                echo "Skipping File:\t\t\t{$file->getFilename()}\n";
            }
        }
        else
        {
            echo "Error: Unexpected file adapter - " . get_class($file->getFilesystem()->getAdapter()) . "\n\n";
            exit;
        }
    }

    echo "\n----------------------------------------------------------------------------------------------------------------------------------------------------------------\nMigration Completed\n----------------------------------------------------------------------------------------------------------------------------------------------------------------\n\n";
    echo "Files In Storage Table:\t\t{$count}\n";
    echo "Files Orphaned:\t\t\t{$countUnknown}\n";
    echo "Files Uploaded:\t\t\t{$countUploaded}\n\n";
});
