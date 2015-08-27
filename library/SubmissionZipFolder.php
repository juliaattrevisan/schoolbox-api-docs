<?php
/**
 * Zips a list of file submissions.
 * Moved out of /www/downloadAll.php
 */

use Schoolbox\Assessment\Component\SubmissionBox\Submission;

class SubmissionZipFolder
{
    /**
     * @param string $folder The name of the zip to create
     * @param Submission\File[] $fileList A list of files to zip
     */
    function __construct($folder, $fileList) {
        $this->tmpName = tempnam(Cfg::get('zip_dir'), 'zip');
        $this->tmpZip = $this->tmpName.'.zip';
        $this->tmpFolder = $this->tmpName.'dir';
        $this->folder = $folder;
        $this->fileList = $fileList;
        $this->allowedChars = "[^0-9a-zA-Z()_-]";
        $this->size = 0;
        $this->maxDepth = 5;
        $this->maxSize = 262144000; //250MB
    }

    function createZip() {
        // TODO make this use http://flysystem.thephpleague.com/adapter/zip-archive/
        $archive = new ZipArchive();
        $archive->open($this->tmpZip, ZipArchive::CREATE);
        mkdir($this->tmpFolder);
        $this->createDirectory($this->tmpFolder, $this->fileList);
        $dir = new RecursiveDirectoryIterator($this->tmpFolder, FilesystemIterator::SKIP_DOTS);

        $i      = 0;
        $ulimit = 250;
        foreach (new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST) as $file) {
            // Close/open archive after 250 additions, to avoid ulimit issues
            if ($i++ % $ulimit === ($ulimit-1)) {
                $archive->close();
                $archive->open($this->tmpZip);
            }

            /** @var SplFileInfo $file */
            // Chop parts of path that match $this->tmpFolder
            $filePath = $file->getPath() . '/' . $file->getFilename();
            $zipPath  = substr($filePath, strlen($this->tmpFolder)+1);
            if ($file->isDir()) {
                $archive->addEmptyDir($zipPath);
            }
            elseif ($file->isFile()) {
                $archive->addFile($filePath, $zipPath);
            }
        }
        $archive->close();
        return true;
    }

    /**
     *
     * @param string $currentDir The temp location to create the zip in
     * @param Submission\File[] $fileList A list of files to zip
     * @param int $depth Unused
     */
    function createDirectory($currentDir, $fileList, $depth = 0) {
        foreach ($fileList as $file) {
            if ($file->getStorage()->exists()) {
                $fileName = sprintf("%s-%s_%s_%s",
                    $file->getOwner()->getLastname(),
                    $file->getOwner()->getFirstname(), // student name
                    $file->getCreatedAt()->getUsingFormat('md-Hi'),   // datetime
                    $file->getBasename()                               // filename
                );
                $this->size = $this->size + $file->getStorage()->getSize();
                $fp = fopen($this->uniqueFileName($currentDir . '/' . $fileName), 'wb');
                stream_copy_to_stream($file->getStorage()->readStream(), $fp);
                fclose($fp);
            }
        }
    }

    function uniqueFileName($fileName, $depth=1) {
        if(is_file($fileName)) {
            $pathParts = pathinfo($fileName);
            $newName = sprintf("%s/%s_%s.%s", $pathParts['dirname'], $pathParts['filename'], $depth, $pathParts['extension']);
            return $this->uniqueFileName($newName, ++$depth);
        }
        else
            return $fileName;
    }

    function cleanUp() {
        unlink($this->tmpName);
        unlink($this->tmpZip);
        $this->removeDir($this->tmpFolder);
    }

    function removeDir($path) {
        // Add trailing slash to $path if one is not there
        if (substr($path, -1, 1) != "/") {
            $path .= "/";
        }
        foreach (glob($path . "*") as $file) {
            if (is_file($file) === TRUE) {
                // Remove each file in this Directory
                unlink($file);
            }
            else if (is_dir($file) === TRUE) {
                // If this Directory contains a Subdirectory, run this Function on it
                $this->removeDir($file);
            }
        }
        // Remove Directory once Files have been removed (If Exists)
        if (is_dir($path) === TRUE) {
            rmdir($path);
        }
    }

    function sendZip() {
        if($this->createZip()) {
            $filename = $this->cleanName($this->folder);
            $filesize = filesize($this->tmpZip);
            header ("Pragma: ");
            header ("Cache-control: ");
            header ("Content-Type: application/x-zip-compressed");
            header ("Content-Disposition: attachment; filename=\"".$filename.".zip\"");
            header ("Content-Length: $filesize");
            readfile($this->tmpZip);

            $this->cleanUp();
        }
        else {
            error(49);
        }
    }

    function cleanName($name, $delim = "_") {
        $filename = preg_replace("/{$this->allowedChars}/", $delim, $name);
        return $filename;
    }
}