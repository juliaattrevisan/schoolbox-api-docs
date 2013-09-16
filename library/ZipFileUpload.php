<?php
/**
 * ZipFileUpload
 *
 * Upload a zip file containing a folder structure with files in it. Extract the
 * files to the specified extractTo directory (usually /tmp by default) with a
 * prefix ('portal_zip_upload')
 *
 * Recursively reads the contents of the zip and also recursively unlinks the
 * stuff that was extracted.
 *
 * Creates Categorys as required (synonymous with folders)
 */

class ZipFileUpload extends DeprecatedModel {
	private $extractTo = '/tmp';
	private $prefix = 'portal_zip_upload_';
	private $path = '';

	/* Show what is being read and processed
	 */
	private $verbose = false;

	/* A list of files which didn't get added due to errors
	 * 	 */
	private $errorFiles = array();

	/**
	 * Constructor
	 * Set up paths and clean up any existing junk
	 */
	public function __construct() {
		parent::DeprecatedModel();

		$this->path = $this->extractTo . '/' . $this->prefix . uniqid();
                $this->allowedFileHandler = new AllowedFile();


		/* Prepare folder: if folder already exists, unlink all the stuff in it
		 * and start it again
		 */
		$this->cleanup();
	}

	/**
	 * Gets the path that stuff will be extracted into
	 *
	 * @return <type>
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * Use verbose mode
	 */
	public function setVerbose() {
		$this->verbose = true;
	}

	/**
	 * Extract files to the path specified. Returns a dirhandle if successfully
	 * extracted files.
	 */
	public function extract($archive) {
		$extractedFiles = $archive->extract(PCLZIP_OPT_PATH, $this->getPath());

		if (!$extractedFiles) {
			die('Could not extract files.');
		}
		else {
			return opendir($this->getPath());
		}

		return false;
	}

	/**
	 * Traverse directory structure. Create Categorys and Files accordingly,
	 * with the permissions specified
	 */
	public function traverse($dir, $parentID) {
		if (!$dh = @opendir($dir)) {
			return;
		}

		/* Begin traversal
		 */
		while (false !== ($obj = readdir($dh))) {
			if (($obj != '.') && ($obj != '..')) {
				$currentPath = $dir . '/' . $obj;

				/* Create category for the current folder, with POSTed
				 * permissions
				 */
				if (is_dir($currentPath)) {
					$categoryHandler = new Category();

					$id = $categoryHandler->create(array(
						'name' => $obj,
						'parent' => $parentID,
						'group'	=> $_POST['group'],
					));

					if ($this->verbose) {
						echo "Creating $currentPath with $id, corresponding to $obj, with parent {$this->dirMap[$dir]}<br>";
					}
				}

				/* If it's a directory, create a Category for it and remember
				 * the new id corresponding to the path. Enter it and deal with
				 * any stuff inside
				 *
				 * Otherwise, it's a file, and we add it to the parent Category
				 */
				if (is_dir($currentPath)) {
					$this->traverse($currentPath, $id);
				}
				else {
					// Ignore filenames starting with a . (these are usually
					// hidden or system files, and should be ignored)
					if (substr($obj, 0, 1) === '.') {
						continue;
					}

					/* For the File table
					 */
					$data = array(
						'category' => $parentID,
						'owner' => $_SESSION['id'],
						'realname' => $obj,
						'description' => $obj,
					);

					/* Since we've already uploaded the file and extracted it,
					 * create a pseudo-$_FILES array containing the tmp_name
					 * which is simply where the file ended up after extraction
					 *
					 * Allows us to use the fileHandler->create() method without
					 * modifying it.
					 */
					$pseudoFileArray = array(
						'name' => $obj,
						'tmp_name' => $currentPath,
					);

					/* Erorr checking. If something couldn't be processed,
					 * remember it, and print it out later
					 */
					$result = $this->allowedFileHandler->isAllowed($obj);

					if (is_array($result)) {
						$this->errorFiles[] = array(
							'file' => $obj,
							'code' => $result['code'],
							'message' => $result['message']
						);

						if ($this->verbose) {
							echo "Error: could not make <strong>$obj</strong> in $parentID<br>";
						}
					}
					else {
						$fileHandler = new File();
						$fileHandler->create($data, $pseudoFileArray);

						if ($this->verbose) {
							echo "Creating <strong>$obj</strong> in $parentID<br>";
						}
					}
				}
			}
		}

		closedir($dh);

		return;
	}

	/**
	 * Remove temp folder that was extracted. Or you can feed in a list of
	 * folders to remove. It will prefix this list with the extractTo value for
	 * a bit of safety.
	 */
	public function cleanup($existing = false) {
		if (is_array($existing)) {
			foreach($existing as $currentPath) {
				$this->unlinkRecursive($this->extractTo . '/' . $currentPath, true);
			}
		}
		else {
			if (is_dir($this->getPath())) {
				$this->unlinkRecursive($this->getPath(), true);
			}
		}
	}

	/**
	 * Recursively delete a directory
	 *
	 * @param string $dir Directory name
	 * @param boolean $deleteRootToo Delete specified top-level directory as well
	 *
	 * Taken from php.net
	 */
	public function unlinkRecursive($dir, $deleteRootToo) {
		if(!$dh = @opendir($dir)) {
			return;
		}

		while (false !== ($obj = readdir($dh))) {
			if($obj == '.' || $obj == '..') {
				continue;
			}

			if (!@unlink($dir . '/' . $obj)) {
				$this->unlinkRecursive($dir . '/' . $obj, true);
			}
		}

		closedir($dh);

		if ($deleteRootToo) {
			@rmdir($dir);
		}

		return;
	}

	/**
	 * Get error data for files which couldn't be uploaded
	 */
	public function getErrors() {
		$errorMessages = array();

		if (count($this->errorFiles)) {
			foreach($this->errorFiles as $currentFile) {
				$query = 'SELECT message FROM error WHERE id = ' . $currentFile['code'];

				$error = $this->db->getRow($query);

				$errorMessages[] = array(
					'file' => $currentFile['file'],
					'message' => str_replace('%MESSAGE%', $currentFile['message'], $error['message']),
				);
			}
		}

		return $errorMessages;
	}
}
?>