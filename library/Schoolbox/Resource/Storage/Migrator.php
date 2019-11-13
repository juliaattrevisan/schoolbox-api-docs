<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource;
use Schoolbox\Resource\Storage;
use League\Flysystem;
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

        // TODO remove this entire class
        $conf             = new Loader();
        $this->storageDir = $conf->get('storage_dir');
        $this->queueDir   = $conf->get('video_dir');

        $this->factory    = new Resource\FilesystemFactory();
        $this->abf        = new Alaress_Byte_Format();

        // intentionally not singleton, so as to keep checkPrevious value separated
        $this->storageMapper  = new Storage\Mapper();
        $this->storageMapper->dePostSave();
        $this->storageMapper->registerCallback(
            Storage\Mapper::CALLBACK_POSTSAVE,
            function($storage) {
                $saveResult = $this->storageMapper->getManager()->save($storage);
                return $saveResult;
            });
        $this->mimetypeMapper = File\MimetypeMapper::getInstance();
        $this->queueMapper    = QueueMapper::getInstance();

        $this->db = Database::getInstance();
        $this->selectStorageId = $this->db->prepare("
            SELECT storage_id FROM storage WHERE hash = :hash
        ");
        $this->updateQueue = $this->db->prepare("
            UPDATE video_queue vq
            SET hash = :hash
            WHERE vq.type = :oldType AND vq.file_id = :oldId
        ");
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
                // catch and log ANY exception, and continue onto the next pattern
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
        $type = substr($fileDir, strrpos($fileDir, '/') + 1);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $fileId   = $pattern->readId($row);
            $basename = $pattern->readFilename($row);

            // for things like submission returns, there might not actually BE a file; skip if this is the case
            if (!$basename) {
                $this->log->warning("No filename for $type.$fileId; skipping");
                continue;
            }

            try {
                $mime = $this->mimetypeMapper->getByFilename($basename);
            }
            catch(\Schoolbox\File\Exception $e) {
                $this->log->warning("Cannot detect mime; skipping $basename");
                continue;
            }

            $filename = mb_pathinfo($basename, PATHINFO_FILENAME);
            $ext      = mb_pathinfo($basename, PATHINFO_EXTENSION);

            // generate a "temporary file"
            $file = $this->storageMapper
                         ->createModel()
                         ->setFilename($filename)
                         ->setExtension($ext)
                         ->setHash(null)
                         ->setMimetype($mime)
                         ->setFilesystem($manager->getFilesystem(Storage\Manager::FS_TEMP))
                         ->setPath("$fileId.dat");


            // TODO find a way to do this in Flysystem
            // assert: file exists, is NOT link, is readable
            $filePath = "$fileDir/$fileId.dat";

            if (file_exists($filePath) && is_readable($filePath)) {

                if (is_link($filePath)) {
                    $this->log->notice("File $type.$fileId is a link, assuming has already been moved");
                    continue;
                }

            } else {
                // actually, this file IS deleted, so mark it as such
                $pattern->flagAsDeleted($fileId);
                $this->log->notice("Flagged $type.$fileId ($filePath) as deleted, file doesn't seem to exist");
                continue;
            }

            // inject the hash into the given table
            $pattern->injectHash($fileId, $file->getHash(), $filename);


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

            try {
                $this->processVideoBecauseReasons($pattern, $file, $fileId);
            } catch (\Exception $e) {
                // catch any and all exceptions caused by video queue processing and ignore them
                $this->log->error("$e");
            }

        }
    }


    /**
     * @deprecated this logic doesn't belong in this class. at all.
     *
     * @param MigrationPattern $pattern
     * @param Model            $file
     * @param int              $fileId
     * @throws ErrorException
     * @throws Exception
     * @throws Flysystem\Exception
     */
    protected function processVideoBecauseReasons(MigrationPattern $pattern, Model $file, $fileId)
    {
        // Migrate the queue
        $queueType = $pattern->getQueueType();

        if (empty($queueType)) {
            return;
        }

        try {
            // What's the hash ID?
            $newId = null;
            $this->selectStorageId->execute(['hash' => $file->getHash()]);
            $newId = $this->selectStorageId->fetchColumn();
            if (!$newId) {
                return;
            }

            $this->updateQueue->execute([
                'hash'   => $file->getHash(),
                'oldType' => $queueType,
                'oldId'   => $fileId,
            ]);

            if (!$this->updateQueue->rowCount()) {
                return;
            }

            // Move and symlink files
            $oldFiles = glob($this->queueDir.'/'.$queueType.'-'.$fileId.'{-,.}*', GLOB_BRACE);
            foreach ($oldFiles as $oldFilepath) {
                $oldPrefix   = $queueType.'-'.$fileId;
                $newPrefix   = $file->getHash();
                $newFilepath = str_replace($oldPrefix, $newPrefix, $oldFilepath);
                $oldFilename = mb_pathinfo($oldFilepath, PATHINFO_FILENAME);
                $newFilename = mb_pathinfo($newFilepath, PATHINFO_FILENAME);

                $this->log->info("Migrating queue file '$oldFilename' to '$newFilename'");

                if (file_exists($newFilepath)) {
                    $this->log->debug(
                        "Queue file '$newFilename' already exists, assuming it is already imported'"
                    );
                    return;

                } elseif (!rename($oldFilepath, $newFilepath)) {
                    throw new Flysystem\Exception("Migrating queue file '$oldFilename' failed.");
                }

                // Point old to new
                if (!symlink($newFilepath, $oldFilepath)) {
                    throw new Flysystem\Exception("Symlinking queue file '$oldFilename' failed.");
                }
            }

        } catch (MapperException $e) {
            // No queue exists, that's okay

        } catch (Flysystem\Exception $e) {
            // for now, just rethrow it
            throw $e;

        } catch (ErrorException $e) {
            // for now, just rethrow it
            throw $e;
        }
    }
}
