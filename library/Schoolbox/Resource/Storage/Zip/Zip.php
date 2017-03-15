<?php
/**
 * Wrapper for handling ZIP files in storage
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Storage\Zip;

use League\Flysystem\Filesystem;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

class Zip
{
    /** @var ConfigLoader */
    private $configLoader;

    /** @var string */
    private $filename;

    /** @var string */
    private $filenameTemp;

    /** @var StorageMapper */
    private $storageMapper;

    /** @var ErrorMapper */
    private $errorMapper;

    /** @var MimetypeMapper */
    private $mimetypeMapper;

    /** @var Filesystem */
    private $zipFilesystem;

    /** @var ZipArchiveAdapter */
    private $zipArchiveAdapter;

    /**
     * Create new ZIP file in temporary storage
     * @param string $filename
     * @return Zip
     */
    public static function CreateTemporaryArchive($filename)
    {
        $zip = new Zip();
        $zip->filename = $filename;
        $zip->zipArchiveAdapter = new ZipArchiveAdapter($zip->getTemporaryFilename());
        $zip->zipFilesystem = new Filesystem($zip->zipArchiveAdapter);
        return $zip;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->configLoader = new ConfigLoader();
        $this->storageMapper = StorageMapper::getInstance();
        $this->errorMapper = ErrorMapper::getInstance();
        $this->mimetypeMapper = MimetypeMapper::getInstance();

        register_shutdown_function([$this, 'shutdown']);
    }

    /**
     * Get a temporary filename
     */
    private function getTemporaryFilename()
    {
        if (empty($this->filenameTemp)) {
            $this->filenameTemp = tempnam($this->configLoader->get('zip_dir'), 'zip.');
        }

        return $this->filenameTemp;
    }

    /**
     * Add local file to ZIP archive
     * @param string $localFile The local file on disk
     * @param string $zipPath The destination path in the ZIP file
     */
    public function addFile($localFile, $zipPath = '')
    {
        $this->zipArchiveAdapter->getArchive()->addFile($localFile, $zipPath);
    }

    /**
     * Add stream to ZIP archive
     * @param resource $stream
     * @param string $zipPath
     */
    public function addStream($stream, $zipPath = '')
    {
        $this->zipFilesystem->getAdapter()->writeStream($zipPath, $stream, $this->zipFilesystem->getConfig());
    }

    /**
     * Persist ZIP file (It only gets written when it is closed)
     */
    public function persist()
    {
        $this->zipArchiveAdapter->getArchive()->close();
        $this->zipArchiveAdapter->getArchive()->open($this->getTemporaryFilename());
    }

    /**
     * Send the zip file to the users browser
     */
    public function download()
    {
        $this->persist();

        $filename = str_replace("\"", "\\\"", $this->filename);

        header("Pragma: ");
        header("Cache-control: private");
        header("Content-Ranges: none");
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header("Content-Length: " . filesize($this->getTemporaryFilename()));
        header("Content-Type: application/octet-stream");

        readfile($this->getTemporaryFilename());
        exit;
    }

    /**
     * Shutdown handler to delete the temporary file from disk if it exists
     */
    public function shutdown()
    {
        $this->zipArchiveAdapter->getArchive()->close();

        if (!empty($this->getTemporaryFilename())) {
            if (file_exists($this->getTemporaryFilename())) {
                unlink($this->getTemporaryFilename());
            }
        }
    }
}
