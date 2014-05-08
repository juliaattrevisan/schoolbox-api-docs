<?php
/**
 * Zips a list of file submissions.
 * Moved out of /www/downloadAll.php
 */
class SubmissionZipFolder {
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
        $archive = new ZipArchive();
        $archive->open($this->tmpZip, ZipArchive::CREATE);
        mkdir($this->tmpFolder);
        $this->createDirectory($this->tmpFolder, $this->folder, $this->fileList);
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

    function createDirectory($currentDir, $folderID, $fileList, $depth = 0) {
        $wrapper = new Alaress\Html\Wrapper();
        if(is_array($fileList)) {
            foreach($fileList as $file) {
                $dataFile = $file['id'].'.dat';

                $fileLocation = Cfg::get('submission_dir') . '/' . $dataFile;
                $fileName = $currentDir . '/' . sprintf("%s-%s_%s_%s",
                    $file['lastname'], $file['firstname'],          // student name
                    date("md-Hi", strtotime($file['submitted'])),   // datetime
                    $file['realname']                               // filename
                );
                if (is_file($fileLocation)) {
                    $this->size = $this->size + filesize($fileLocation);
                    $fileName = $this->uniqueFileName($fileName); //ensure filename is unique else puts numbers in filename
                    copy(Cfg::get('submission_dir') .'/'.$dataFile, $this->uniqueFileName($fileName));
                }
                elseif (strlen($file['body']) > 0) {
                    // This might be an online submission.
                    // Copy the file's contents to the filename.
                    $fileName .= '.html';
                    $wrapper
                        ->setTitle($file['realname'])
                        ->setBody($file['body'])
                        ->loadBaseHrefFromServerVars($_SERVER);
                    $content = $wrapper->render();
                    $this->size = $this->size + strlen($content);
                    file_put_contents($this->uniqueFileName($fileName), $content);
                }
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