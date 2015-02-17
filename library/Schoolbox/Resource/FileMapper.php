<?php
/**
 * Mapper for files in the resource browser.
 *
 * Differs from Schoolbox\File in that files of this type display in the
 * resource browser, files in Schoolbox\File don't.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Resource;
use Schoolbox;
use Schoolbox\FileInterface;
use Schoolbox\Mapper;
use Schoolbox\Storage;
use Schoolbox\StorageMapper;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;

class FileMapper extends Mapper implements FileLocatorInterface, Schoolbox\StorageMapperInterface {

	/**
	 * @var \Schoolbox\StorageMapper
	 */
	protected $storage_mapper;

	public function setup() {
		parent::setup();
		$this->storage_mapper = new StorageMapper();
	}

	/**
	 * @param  \Schoolbox\StorageMapper $storage_mapper
	 * @return $this
	 */
	public function setStorageMapper($storage_mapper) {
		$this->storage_mapper = $storage_mapper;
		return $this;
	}

	/**
	 * @return \Schoolbox\StorageMapper
	 */
	public function getStorageMapper() {
		return $this->storage_mapper;
	}

	protected function mapping() {
		$this->hasTable('file');
		$this->hasModel('\\Schoolbox\\Resource\\File');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'realname',
			'property'   => 'filename',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'comment',
			'property'   => 'comment',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'description',
			'property'   => 'description',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'hidden',
			'property'   => 'hidden',
			'type'       => self::TYPE_BOOLEAN,
		));
		$this->hasMap(array(
			'column'     => 'category',
			'property'   => 'folder',
			'type'       => self::TYPE_MODEL,
            'join'       => 'category'
		));
		$this->hasMap(array(
			'column'     => 'file_size',
			'property'   => 'filesize',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'owner',
			'property'   => 'owner',
			'type'       => self::TYPE_MODEL,
            'join'       => 'user',
		));
		$this->hasMap(array(
			'column'            => 'submitted',
			'property'          => 'submitted',
			'type'              => self::TYPE_DATETIME,
			'created_timestamp' => true,
		));
		$this->hasMap(array(
			'column'            => 'modified',
			'property'          => 'modified',
			'type'              => self::TYPE_DATETIME,
			'updated_timestamp' => true,
		));
		$this->hasMap(array(
			'column'     => 'deleted',
			'property'   => 'deleted',
			'type'       => self::TYPE_BOOLEAN,
		));
		$this->hasSoftDeleteBooleanField('deleted');

		$this->hasJoin('user', array(
             'mapper'    => '\\Schoolbox\\UserMapper',
             'this_key'  => 'owner',
             'other_key' => 'id',
		));

        // Relation to Allowed Files

        $hydrator = new Schoolbox\Column\Model();
        $hydrator->setIdentifierProperty('mime');
        $this->hasMap(array(
                'column'     => 'mime_type',
                'property'   => 'mimeType',
                'hydrator'   => $hydrator,
                'join'       => 'allowedFileType',
            ));

        $this->hasJoin('allowedFileType', array(
                'mapper'    => '\\Schoolbox\\File\\MimetypeMapper',
                'this_key'  => 'mime_type',
                'other_key' => 'mime',
            ));

        $this->hasJoin('category', array(
            'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
            'this_key'  => 'category',
            'other_key' => 'id'
        ));

        $this->registerCallback('preSave', array($this, 'preSave'));
        $this->registerCallback('postSave', array($this, 'postSave'));

	}


    /**
     * Retreives a schoolbox file system file with a mime type object
     *
     * @param int $id
     * @throws \Schoolbox\Mapper\Exception
     * @return \Schoolbox\Resource\File
     */
    public function findById($id) {

        $file = false;

	    /** @var MimetypeMapper $mimeTypeMapper */
        $mimeTypeMapper = $this->getJoinMapper('allowedFileType');

        $query = "
			SELECT {$this->getColumnString('file')},
			      {$mimeTypeMapper->getColumnString('m')}
			FROM `{$this->table}`
			LEFT JOIN {$this->getJoinSql('allowedFileType', 'file', 'm')}
			WHERE file.id = ?
		";

        $statement = $this->database->prepare($query);
        $statement->execute(array($id));

        if (($row = $statement->fetch()) !== false) {
            $file = $this->hydrate($row, 'file');

			$mimeType = $mimeTypeMapper->hydrate($row, 'm');

			/* If this hydrated as a null-filled object, fandangle around it
			 * (SB-3911)
			 */
			if (!$mimeType->getFileTypeID()) {
				$mimeType = $mimeTypeMapper->getUnknownMime();
			}

            $file->setMimeType($mimeType);
        }

        if (!($file instanceof File)) {
            throw new Schoolbox\Mapper\Exception('Instance of object not found');
        }

        return $file;
    }

	/**
	 * Copies this file to the storage engine, or returns the existing copy if
	 * it's already there.
	 *
	 * @param  File $file The file object to copy to storage.
	 * @throws Exception If the file stored in $file can't be loaded.
	 * @return Storage The new storage item or existing item.
	 */
	public function copyToStorage(File $file) {
		$location = $file->getFileLocation();
		if (!(is_file($location) && is_readable($location))) {
			throw new Exception(
				'The file could not be loaded - try deleting it and re-attaching it.',
				Exception::CODE_COPY_TO_STORAGE_FAILED
			);
		}
		/** @var Storage $storage */
		$storage  = $this->storage_mapper->createModel();
		$storage
			->setFilename($file->getFilename())
			->setTempLocation($location)
			->setUploadedBy($file->getOwner());
		$storage->loadHashFromTempLocation();
		$storage->loadFilesizeFromTempLocation();

		try {
			$existing = $this->storage_mapper->findByHash($storage->getHash());
		}
		catch (Mapper\Exception $ex) {
			return $storage;
		}
		catch (Exception $ex) {
			return $storage;
		}
		return $existing;
	}


    /**
     * Checks file can be saved and it passes all the required checks
     * @param File $file
     * @throws \Schoolbox\File\Exception
     */
    protected function preSave(File $file) {
        // check if file exists
        if (is_file($file->getTempFileLocation()) && is_readable($file->getTempFileLocation())) {
            // Check if file attached if valid (mime and size)
            try {
                $file->setMimeType( MimetypeMapper::getInstance()->getByFilename($file->getFilename()) );

                /** @var Mimetype $mimetype */
                if ($file->getFilesize() > $file->getMimeType()->getMaxFileSize()) {
                    throw new Schoolbox\File\Exception('File is too large', Schoolbox\File\Exception::CODE_FILE_TOO_LARGE);
                }
            } catch (Exception $e) {
                throw new Schoolbox\File\Exception('File type not permitted', Schoolbox\File\Exception::CODE_MIMETYPE_NOT_FOUND);
            }
            
            // check if we can save the file
            if (!is_writable($this->getSaveLocation())) {
                throw new Schoolbox\File\Exception('Cannot save file', Schoolbox\File\Exception::CODE_CANNOT_WRITE);
            }
        }
    }

    /**
     * Save the file to the correct location
     * Setup video processing
     *
     * @param File $file
     * @param File $previous
     * @throws \Schoolbox\File\Exception
     */
    protected function postSave(File $file, $previous = null) {
        // copy file into location
        if( is_readable($file->getTempFileLocation()) && $file->getTempFileLocation() != $file->getFileLocation()) {

            $filename = $this->getSaveLocation() . $file->getId() . '.dat';
            @unlink($filename);

            if (!copy($file->getTempFileLocation(), $filename)) {
                throw new Schoolbox\File\Exception('Cannot save file', Schoolbox\File\Exception::CODE_CANNOT_WRITE);
            }
            // If video add to video processing queue */
            if ($file->getMimeType()->isVideo() || $file->getMimetype()->isAudio()) {
                $mapper = new Schoolbox\Video\QueueMapper();
                $videoQ = new Schoolbox\Video\Queue();
                $videoQ->setFileID($file->getId());
                $videoQ->setStatus(Schoolbox\Video\QueueMapper::STATUS_PENDING);
                $videoQ->setType(Schoolbox\Video\QueueMapper::FILE_TYPE_SBFS);

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
        return \Config::get('file_dir') . '/';
    }


    /**
     * @param array $columnList
     * @param array $options
     * @return array
     */
    protected function buildWherePart($columnList, $options)
    {
        $parts = parent::buildWherePart($columnList, $options);

        if (isset($options['mime'])) {
            $mime = str_replace('*', '%', $options['mime']);
            $parts['where'][] = sprintf("%s LIKE %s",
                $this->database->quoteIdentifier('mime_type'),
                $this->database->quote($mime)
            );
        }

        return $parts;
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
            'category' => $nodeId
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

        /** @var File $file */
        $file = $this->createModel();
        $file->setTempFileLocation($tempLocation);
        $file->setOwner($this->getJoinMapper('user')->createModel()->setId($userId));
        $file->setFilename($filename);
        $file->setFilesize(filesize($tempLocation));

        // TODO either rename this method entirely, or find a way to pass enough data thru to persist this object
        return $file;
    }

}