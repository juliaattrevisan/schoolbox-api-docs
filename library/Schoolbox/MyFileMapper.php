<?php
/**
 * Mapper for the File model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;

use Schoolbox\File\MimetypeMapper;

class MyFileMapper extends Resource\My\File\Mapper implements Resource\FileLocatorInterface, StorageMapperInterface {

	protected function mapping () {

		$this->registerCallback('postSave', array($this, 'postSave'));
	}

	/**
	 * Find all image files owned by a particular user
	 *
	 * @param $userId
	 * @return Collection
	 */
	public function findAllImagesByUser($userId) {
		$query = "
			SELECT
				{$this->getColumnString('mf')}
			FROM `{$this->table}` mf
			WHERE mf.mime_type LIKE '%image/%'
			AND mf.deleted IS FALSE
			AND mf.owner = :userId
		";

		$statement = $this->database->prepare($query);
		$statement->bindParam(':userId', $userId, \PDO::PARAM_INT);
		$statement->execute();

		$collection = new Collection();

		while ($row = $statement->fetch()) {
			$collection->add($this->hydrate($row, 'mf'));
		}

		return $collection;
	}

	/**
	 * Save the file to the correct location
	 * Setup video processing
	 *
	 * @param MyFile $file
	 * @param object $previous
	 * @throws File\Exception
	 */
	protected function postSave(MyFile $file, $previous = null) {

		// copy file into location
		if( is_readable($file->getTempFileLocation())) {
			$filename = $file->getFileLocation();
			@unlink($filename);

			$mimetype = File\MimetypeMapper::getInstance()->findOneByColumn('mime', $file->getMime());

			if (!copy($file->getTempFileLocation(), $filename)) {
				throw new File\Exception('Cannot save file', File\Exception::CODE_CANNOT_WRITE);
			}
			// If video add to video processing queue */
			if ($mimetype->isVideo()) {
				$mapper = new Video\QueueMapper();
				$videoQ = new Video\Queue();
				$videoQ->setFileID($file->getId());
				$videoQ->setStatus(Video\QueueMapper::STATUS_PENDING);
				$videoQ->setType(Video\QueueMapper::FILE_TYPE_MYFILE);

				$mapper->save($videoQ);
			}
		}

		// TODO: log action MD?
	}

	/**
	 * Returns the location where files should be saved
	 * @return string
	 */
	private function getSaveLocation() {
		return $this->getConfigLoader()->get('myfile_dir') . '/';
	}


    /**
     * Return the files contained within the folder with the given ID
     *
     * @param int $nodeId
     * @return FileInterface[]
     */
    public function findByFolderId($nodeId)
    {
        return $this->findByColumnList(array(
            'parent' => $nodeId,
            'owner'  => $_SESSION['id'] // TODO: FIX. DO NOT MERGE THIS
        ));
    }


    /**
     * Convenience method for creating and saving a file via this mapper.
     *
     * @param string $filename     the file's name.
     * @param string $tempLocation the location of the file to save.
     * @param int    $userId       the user to associate with the file.
     * @throws \Schoolbox\File\Exception if the upload was not allowed.
     * @return FileInterface
     */
    public function upload($filename, $tempLocation, $userId)
    {
        // check it's not already in SBFS
        if (preg_match('`^' . $this->getSaveLocation() . '([0-9]+)`', $tempLocation, $matches)) {
            return $this->findById($matches[1]);
        }

        /** @var MyFile $file */
        $file = $this->createModel();
        $file->setTempFileLocation($tempLocation);
        $file->setOwner($this->getJoinMapper('owner')->createModel()->setId($userId));
        $file->setFilename($filename);
        $file->setFilesize(filesize($tempLocation));
        $file->setMimetype(MimetypeMapper::getInstance()->getByFilename($filename));

        // TODO either rename this method entirely, or find a way to pass enough data thru to persist this object
        return $file;
    }

}
