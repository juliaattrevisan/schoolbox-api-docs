<?php
/**
 * Mapper for the File model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;
use Schoolbox;
use Schoolbox\Mapper;

class MyFileMapper extends Mapper implements Schoolbox\Resource\FileLocatorInterface {

	protected function mapping () {
		$this->hasTable('myFile');
		$this->hasModel('\\Schoolbox\\MyFile');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));

		$this->hasMap(array(
			'column'     => 'parent',
			'property'   => 'parent',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'owner',
			'property'   => 'owner',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'realname',
			'property'   => 'filename',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'submitted',
			'property'   => 'createdAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'description',
			'property'   => 'description',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'file_size',
			'property'   => 'filesize',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'mime_type',
			'property'   => 'mime',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'deleted',
			'property'   => 'deletedAt',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasJoin('allowedFileType', array(
			'mapper'    => '\\Schoolbox\\File\\MimetypeMapper',
			'this_key'  => 'mime_type',
			'other_key' => 'mime',
		));

		$this->hasJoin('owner', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'owner',
			'other_key' => 'id',
		));

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

		$collection = new \Schoolbox\Collection();

		while ($row = $statement->fetch()) {
			$collection->add($this->hydrate($row, 'mf'));
		}

		return $collection;
	}

	/**
	 * Save the file to the correct location
	 * Setup video processing
	 *
	 * @param \Schoolbox\MyFile $file
	 * @param object $previous
	 * @throws File\Exception
	 */
	protected function postSave(Schoolbox\MyFile $file, $previous = null) {

		// copy file into location
		if( is_readable($file->getTempFileLocation())) {
			$filename = $file->getFileLocation();
			@unlink($filename);

			$mimetype = Schoolbox\File\MimetypeMapper::getInstance()->findOneByColumn('mime', $file->getMime());

			if (!copy($file->getTempFileLocation(), $filename)) {
				throw new Schoolbox\File\Exception('Cannot save file', Schoolbox\File\Exception::CODE_CANNOT_WRITE);
			}
			// If video add to video processing queue */
			if ($mimetype->isVideo()) {
				$mapper = new Schoolbox\Video\QueueMapper();
				$videoQ = new Schoolbox\Video\Queue();
				$videoQ->setFileID($file->getId());
				$videoQ->setStatus(Schoolbox\Video\QueueMapper::STATUS_PENDING);
				$videoQ->setType(Schoolbox\Video\QueueMapper::FILE_TYPE_MYFILE);

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
		return \Config::get('myfile_dir') . '/';
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
}
