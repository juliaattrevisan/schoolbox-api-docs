<?php
/**
 * Wrapper for handling ZIP files in storage
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Storage\Zip;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as Storage;
use SplFileInfo;
use ZipArchive;

class Zip
{
    /** @var Storage */
    private $storageObject;

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

    /** @var ZipArchive */
    private $archive;

    /** @var int */
    private $limit = 250;

    /**
     * @param $filename
     * @param string|null $filename ZIP filename
     * @return $this
     */
    public static function CreateArchive($filename)
    {
        $zip = new static();
        $zip->create($filename);

        return $zip;
    }

    /**
     * Create zip file in storage
     * @param string|null $hash Optional hash, if supplied will load existing ZIP from storage
     */
    public function __construct($hash = null)
    {
        $this->configLoader = new ConfigLoader();
        $this->storageMapper = StorageMapper::getInstance();
        $this->errorMapper = ErrorMapper::getInstance();
        $this->mimetypeMapper = MimetypeMapper::getInstance();

        register_shutdown_function([$this, 'shutdown']);

        if (!empty($hash)) {
            $this->load($hash);
        }
    }

    /**
     * Set a temporary filename
     */
    private function getTemporaryFilename()
    {
        if (empty($this->filenameTemp)) {
            $this->filenameTemp = tempnam($this->configLoader->get('zip_dir'), 'zip');
        }

        return $this->filenameTemp;
    }

    /**
     * Create new ZIP file in temporary storage
     * @param string $filename
     */
    private function create($filename)
    {
        $this->filename = $filename;

        $this->archive = new ZipArchive();
        $this->archive->open($this->getTemporaryFilename(), ZipArchive::CREATE);
    }

    /**
     * @param string $localFile     The local file on disk
     * @param string $zipPath       The destination path in the ZIP file
     */
    public function addFile($localFile, $zipPath = '')
    {
        $zipPath = rtrim($zipPath, '/');

        // Remove the local path from the filename

        $filePath = pathinfo($localFile, PATHINFO_DIRNAME);
        $basename = substr($localFile, strlen($filePath) + 1);

        if ($zipPath != "") {
            $this->archive->addEmptyDir($zipPath);
            $zipPath .= "/{$basename}";
        } else {
            $zipPath = $basename;
        }
       // echo "2 {$filePath} => {$zipPath}<br>";

        $this->archive->addFile($localFile, $zipPath);
    }

    /**
     * @param string $localPath     The local path of the directory on disk
     * @param string $zipPath       The destination path in the ZIP file
     */
    public function addDirectory($localPath, $zipPath = '')
    {
        $dir = new RecursiveDirectoryIterator($localPath, FilesystemIterator::SKIP_DOTS);

        $count = 0;

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST) as $file) {

            // Close and reopen the archive after 250 additions to avoid ulimit issues

            if ($count++ % $this->limit === ($this->limit - 1)) {
                $this->archive->close();
                $this->archive->open($this->getTemporaryFilename());
            }

            // Remove the local path from the filename

            $filePath = $file->getPath() . '/' . $file->getFilename();
            $basename = substr($filePath, strlen($localPath) +  1);

            if ($file->isFile()) {
                if (file_exists($filePath)) {
                    $this->archive->addFile($filePath, "{$zipPath}/{$basename}");
                }
            }
        }
    }

    /**
     * Load existing ZIP file from storage
     * @param $hash
     */
    public function load($hash)
    {
        try
        {
            // Locate the storage object

            $this->storageObject = $this->storageMapper->findByHash($hash);
        }
        catch (MapperException $e)
        {
            // Couldn't find the requested storage object

            throw $this->errorMapper->generateException(ErrorMapper::ERROR_FILE_NOT_FOUND);
        }

        // Don't know that we'll actually need this after its been put in storage, but sticking it in for completeness of "hydration"

        $this->filename = "{$this->storageObject->getFilename()}.{$this->storageObject->getExtension()}";

        // Write the storage object to the local disk for processing

        file_put_contents($this->getTemporaryFilename(), $this->storageObject->readStream());

        // And open the archive up so we can use it

        $this->archive = new ZipArchive();

        if (!$this->archive->open($this->getTemporaryFilename()) !== TRUE) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_FILE_NOT_FOUND);
        }
    }

    /**
     * Save ZIP file back to storage
     */
    public function save()
    {
        // Check if there was an existing storage object, if not create one

        if (empty($this->storageObject)) {
            // Create new storage object
            $this->storageObject = $this->storageMapper->accept($this->filename, $this->filenameTemp);
        } else {
            // Update existing storage object
            $this->storageMapper->save($this->storageObject);
        }
    }

    /**
     * Send the zip file to the users browser
     */
    public function download()
    {
        // Close the archive
        $this->archive->close();

        // Escape double-quotes in filename so we don't baulk the content-disposition header
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
        if (!empty($this->getTemporaryFilename())) {
            if (file_exists($this->getTemporaryFilename())) {
                unlink($this->getTemporaryFilename());
            }
        }
    }
}
