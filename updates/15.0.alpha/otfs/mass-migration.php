<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Config\Loader;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Resource\Storage;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel('Mass Migration', [
    'verbose' => [ 'v', 'v', 'v' ]
]);

$kernel->run(function ($di, $loop, $log, $opts) {
    /**
     * @var Di\Container    $di
     * @var LoopInterface   $loop
     * @var LoggerInterface $log
     * @var array           $opts
     */

    $conf       = new Loader();
    $fileDir    = $conf->get('file_dir');
    $storageDir = $conf->get('storage_dir');

    $factory    = new Schoolbox\Resource\FilesystemFactory();
    $manager    = new Storage\Manager($factory->buildSpecific($storageDir, $fileDir), new Alaress_Byte_Format());

    $storageMapper  = Storage\Mapper::getInstance()->setManager($manager);
    $mimetypeMapper = MimetypeMapper::getInstance();

    $db         = Database::getInstance();
    $fetchIds   = $db->query("SELECT id, realname FROM file WHERE deleted = ? AND hash = ''", [ 0 ]);
    $injectHash = $db->prepare("UPDATE file SET hash = :hash WHERE id = :id");
    $setDeleted = $db->prepare("UPDATE file SET deleted = 1 AND deleted_at = '1999-12-31 23:59:59' WHERE id = ?");

    while ($row = $fetchIds->fetch(PDO::FETCH_ASSOC)) {

        $fileId = $row['id'];
        $mime   = $mimetypeMapper->getByFilename($row['realname']);
        $ext    = pathinfo($row['realname'], PATHINFO_EXTENSION);

        // generate a "temporary file"
        $file = $storageMapper->createModel()
                              ->setExtension($ext)
                              ->setHash(null)
                              ->setMimetype($mime)
                              ->setFilesystem($manager->getFilesystem(Storage\Manager::FS_TEMP))
                              ->setPath("$fileId.dat");



        // TODO find a way to do this in Flysystem
        // assert: file exists, is NOT link, is readable
        $filePath = "$fileDir/$fileId.dat";
        if (!(file_exists($filePath) && !is_link($filePath) && is_readable($filePath))) {
            // actually, this file IS deleted, so mark it as such
            $setDeleted->execute([ $fileId ]);
            $log->notice("Flagged file.$fileId as deleted, file doesn't seem to exist");
            continue;
        }



        // inject the hash into the given table
        $injectHash->execute([
            'id'   => $fileId,
            'hash' => $file->getHash()
        ]);

        $log->info("Attached hash {$file->getHash()} to file.$fileId");

        // save the storage file, which will also copy the actual file into the new storage
        try {
            $storageMapper->save($file);

            $storagePath = "$storageDir/{$file->getPath()}";

            // check the file made it from A to B (which will auto-dedupe)
            if (file_exists($storagePath)) {
                $log->debug("File $storagePath exists, as expected");
            } else {
                throw new League\Flysystem\Exception("Transfer of '$fileDir/$fileId.dat' to '$storagePath' failed.");
            }

            // final step is to symlink back into the old storage mechanism
            unlink($filePath);
            symlink($storagePath, $filePath);

        } catch (League\Flysystem\Exception $e) {
            // for now, just rethrow it
            throw $e;
        } catch (ErrorException $e) {
            // for now, just rethrow it
            throw $e;
        }
    }

});



