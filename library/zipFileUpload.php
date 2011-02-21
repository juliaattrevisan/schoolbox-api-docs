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
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';

class ZipFileUpload {
	private $extractTo = '/tmp';
	private $prefix = 'portal_zip_upload_';
	private $path = '';

	/* Map directory paths to category ids, because folders aren't always read
	 * in the nicest order.
	 */
	public $dirMap = array();

	/**
	 * Set up paths and clean up any existing junk
	 */
	public function __construct() {
		$this->path = $this->extractTo . '/' . $this->prefix . uniqid();

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
	 * Traverse directory structure
	 */
	public function traverse($dir) {
		if (!$dh = @opendir($dir)) {
			return;
		}

		while (false !== ($obj = readdir($dh))) {
			if (($obj != '.') && ($obj != '..')) {
				$currentPath = $dir . '/' . $obj;

				if (is_dir($currentPath)) {
					$categoryHandler = new Category();

//					$id = $categoryHandler->create(array(
//						'name' => $obj,
//						'parent' => $this->dirMap[$dir],
//					));

					$id = rand(100,1000);

					$this->dirMap[$currentPath] = $id;

					echo "Creating $currentPath with $id, corresponding to $obj, with parent {$this->dirMap[$dir]}<br>";
				}

				/* If it's a directory, create a Category for it and remember
				 * the new id corresponding to the path.
				 *
				 * Otherwise, it's a file, and we add it to the parent Category
				 */
				if (is_dir($currentPath)) {
//					$subid = $categoryHandler->create(array(
//						'name' => $obj,
//						'parent' => $this->dirMap[$dir],
//					));
					$subid = rand(10000,100000);

//					echo "Creating folder $subid containing <strong>$obj</strong> in parent {$this->dirMap[$dir]}<br>";

					$this->traverse($currentPath);
				}
				else {
//					echo "Creating <strong>$obj</strong> \t\t in {$this->dirMap[$dir]}<br>";
				}
			}
		}

		closedir($dh);

		return;
	}

	/**
	 * Remove temp folder that was extracted. Or you can feed in a list of
	 * folders to remove. It will prefix this list with the extractTo value for
	 * a bit of safety
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
}
?>
