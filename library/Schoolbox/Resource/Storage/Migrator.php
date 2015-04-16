<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Storage;


use Alaress_Byte_Format;
use Database;
use ErrorException;
use Exception;
use PDO;
use Psr\Log\LoggerInterface;
use Schoolbox\Config\Loader;
use Schoolbox\File;
use Schoolbox\Resource;
use Schoolbox\Resource\Storage;
use League\Flysystem;
use Schoolbox\Video\Queue;
use Schoolbox\Video\QueueMapper;

/**
 * Class Migrator
 *
 * For every supplied MigrationPattern, Migrator transitions files safely from the given brokenFS storage to OTFS
 *
 * @package Schoolbox\Resource\Storage
 */
class Migrator
{

    /**
     * @param LoggerInterface $log
     */
    public function __construct(LoggerInterface $log)
    {
        $this->log        = $log;

        $conf             = new Loader();
        $this->storageDir = $conf->get('storage_dir');
        $this->queueDir   = $conf->get('video_dir');

        $this->factory    = new Resource\FilesystemFactory();
        $this->abf        = new Alaress_Byte_Format();

        // intentionally not singleton, so as to keep checkPrevious value separated
        $this->storageMapper  = new Storage\Mapper();
        $this->storageMapper->checkPrevious(false);

        $this->mimetypeMapper = File\MimetypeMapper::getInstance();
        $this->queueMapper    = QueueMapper::getInstance();

        $this->db = Database::getInstance();

    }


    /**
     * @param MigrationPattern[] $patterns
     */
    public function migrateEach(array $patterns)
    {
        foreach ($patterns as $pattern) {
            try {
                $this->migrate($pattern);
            } catch (\Exception $e) {
                $this->log->critical("$e");
            }
        }
    }


    /**
     * @param MigrationPattern $pattern
     * @throws File\Exception
     * @throws Flysystem\Exception
     * @throws ErrorException
     * @throws Exception
     */
    public function migrate(MigrationPattern $pattern)
    {
        $fileDir = $pattern->getFileDir();

        $manager = new Storage\Manager($this->factory->buildSpecific($this->storageDir, $fileDir), $this->abf);
        $this->storageMapper->setManager($manager);

        $stmt = $pattern->getFetchStatement();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $fileId   = $pattern->readId($row);
            $filename = $pattern->readFilename($row);

            if (!$filename) {
                $this->log->warning("No filename, cannot detect mime; skipping");
                continue;
            }

            $mime     = $this->mimetypeMapper->getByFilename($filename);
            $ext      = pathinfo($filename, PATHINFO_EXTENSION);

            // generate a "temporary file"
            $file = $this->storageMapper
                         ->createModel()
                         ->setExtension($ext)
                         ->setHash(null)
                         ->setMimetype($mime)
                         ->setFilesystem($manager->getFilesystem(Storage\Manager::FS_TEMP))
                         ->setPath("$fileId.dat");


            // TODO find a way to do this in Flysystem
            // assert: file exists, is NOT link, is readable
            $filePath = "$fileDir/$fileId.dat";
            $type     = substr($fileDir, strrpos($fileDir, '/') + 1);

            if (file_exists($filePath) && is_readable($filePath)) {

                if (is_link($filePath)) {
                    $this->log->notice("File $type.$fileId is a link, assuming has already been moved");
                    continue;
                }

            } else {
                // actually, this file IS deleted, so mark it as such
                $pattern->flagAsDeleted($fileId);
                $this->log->notice("Flagged $type.$fileId as deleted, file doesn't seem to exist");
                continue;
            }

            // inject the hash into the given table
            $pattern->injectHash($fileId, $file->getHash());


            $this->log->info("Attached hash {$file->getHash()} to $type.$fileId");

            // save the storage file, which will also copy the actual file into the new storage
            try {
                $this->storageMapper->save($file);

                $storagePath = "$this->storageDir/{$file->getPath()}";

                // check the file made it from A to B (which will auto-dedupe)
                if (file_exists($storagePath)) {
                    $this->log->debug("File $storagePath exists, as expected");
                } else {
                    throw new Flysystem\Exception("Transfer of '$fileDir/$fileId.dat' to '$storagePath' failed.");
                }

                // final step is to symlink back into the old storage mechanism
                unlink($filePath);
                symlink($storagePath, $filePath);

            } catch (Flysystem\Exception $e) {
                // for now, just rethrow it
                throw $e;
            } catch (ErrorException $e) {
                // for now, just rethrow it
                throw $e;
            }

            // Migrate the queue
            $queueType = $pattern->getQueueType();
            if (isset($queueType)) {
                try {
                    /** @var Queue $queue */
                    $queue = $this->queueMapper->findOneByColumnList(
                        [
                            'type'    => $queueType,
                            'file_id' => $fileId,
                        ]
                    );
                    // Update the queue and hope to god it's not processing
                    $this->log->info("Migrating queue for $type.$fileId to storage.{$file->getHash()}");
                    $queue
                        ->setId($file->getId())
                        ->setType(QueueMapper::FILE_TYPE_STORAGE)
                    ;
                    $this->queueMapper->save($queue);

                    // Move and symlink files
                    foreach (glob($this->queueDir.'/'.$queueType.'-'.$fileId.'{-,.}*', GLOB_BRACE) as $oldFilepath) {
                        $oldPrefix   = $queueType.'-'.$fileId;
                        $newPrefix   = QueueMapper::FILE_TYPE_STORAGE.'-'.$file->getId();
                        $newFilepath = str_replace($oldPrefix, $newPrefix, $oldFilepath);
                        $oldFilename = pathinfo($oldFilepath, PATHINFO_FILENAME);
                        $newFilename = pathinfo($newFilepath, PATHINFO_FILENAME);

                        $this->log->info("Migrating queue file '$oldFilename' to '$newFilename'");
                        if (!rename($oldFilepath, $newFilepath)) {
                            throw new Flysystem\Exception("Migrating queue file '$oldFilename' failed.");
                        }
                        if (!symlink($newFilename, $oldFilename)) {
                            throw new Flysystem\Exception("Symlinking queue file '$oldFilename' failed.");
                        }
                    }
                } catch (\Schoolbox\Mapper\Exception $e) {
                    // No queue exists, that's okay
                }
            }
        }

    }
} 