<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once('../include/pclzip.lib.php');

$page = new SitePage;

class ZipFolder {
   function ZipFolder($folder, $fileList) {
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
      $archive = new PclZip($this->tmpZip);

      mkdir($this->tmpFolder);
      $this->createDirectory($this->tmpFolder, $this->folder, $this->fileList);
      $archive->create($this->tmpFolder.'/',PCLZIP_OPT_REMOVE_PATH, $this->tmpFolder);

      return true;
   }

    function createDirectory($currentDir, $folderID, $fileList, $depth = 0) {
        if(is_array($fileList)) {
            foreach($fileList as $file) {
                $dataFile = $file['id'].'.dat';

                $fileLocation = Cfg::get('submission_dir') . '/' . $dataFile;

                if(is_file($fileLocation)) {
                    $this->size = $this->size + filesize($fileLocation);

                    $fileName = $currentDir . '/' . sprintf("%s-%s_%s_%s",
                        $file['lastname'], $file['firstname'],          // student name
                        date("md-Hi", strtotime($file['submitted'])),   // datetime
                        $file['realname']                               // filename
                    );

                    $fileName = $this->uniqueFileName($fileName); //ensure filename is unique else puts numbers in filename

                    copy(Cfg::get('submission_dir') .'/'.$dataFile, $fileName);
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


if(is_numeric($_GET['id'])) {
   $box_id = $_GET['id'];

   // check that we have read access to the submissions directory
   if(!is_readable(Cfg::get('submission_dir') . '/')) {
       error(9, "the requested submissions could not be read.<br />Please try again, and if the problem persists contact your system administrator.");
   }

   $subBoxHandler = new Submission();

   $submissionBox = $subBoxHandler->getByID($box_id);
   $zipName = Scrub::text($submissionBox['name']);

   $submissionBoxOwner = $subBoxHandler->getBoxOwner($box_id);

   if ($submissionBoxOwner['owner'] == $_SESSION['id']) {
      $submissionList = $subBoxHandler->getAllForBox($box_id);

      $zip = new ZipFolder($zipName, $submissionList);
      $zip->sendZip();
   }
   else {
      error(5);
   }
}
?>