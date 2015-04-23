<?php
/**
 * Mapper for the File model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;
use Alaress_Byte_Format;
use Schoolbox;
use Schoolbox\Mapper;
use Schoolbox\File\MimetypeMapper;

/**
 * Class StorageMapper
 *
 * @deprecated
 * @package Schoolbox
 */
class StorageMapper extends Mapper implements StorageMapperInterface {
	protected function mapping () {
		$this->hasTable('storage');
		$this->hasModel('\\Schoolbox\\Storage');
		$this->hasMap(array(
			'column'     => 'storage_id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'filename',
			'property'   => 'filename',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'hash',
			'property'   => 'hash',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'filesize',
			'property'   => 'filesize',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'uploaded_by',
			'property'   => 'uploadedBy',
			'type'       => self::TYPE_MODEL,
            'join'       => 'user'
		));
		$this->hasMap(array(
			'column'            => 'uploaded_on',
			'property'          => 'uploadedOn',
			'type'              => self::TYPE_DATETIME,
			'created_timestamp' => true,
		));
        $this->hasMap(array(
            'column'            => 'last_accessed',
            'property'          => 'lastAccessed',
            'type'              => self::TYPE_DATETIME,
            'created_timestamp' => true,
        ));
        $this->hasMap(array(
            'column'            => 'expired_at',
            'property'          => 'expiredAt',
            'type'              => self::TYPE_DATETIME,
        ));

        $this->hasSoftDeleteDateField('expired_at');

        // Relation to Allowed Files

        $hydrator = new Schoolbox\Column\Model();
        $hydrator->setIdentifierProperty('mime');
        $this->hasMap(array(
                'column'     => 'mime',
                'property'   => 'mimetype',
                'hydrator'   => $hydrator,
                'join'       => 'allowedFileType',
        ));

        $this->hasJoin('allowedFileType', array(
                'mapper'    => '\\Schoolbox\\File\\MimetypeMapper',
                'this_key'  => 'mime',
                'other_key' => 'mime',
            ));

        $this->hasJoin('user', array(
            'mapper'    => '\\Schoolbox\\UserMapper',
            'this_key'  => 'uploaded_by',
            'other_key' => 'id'
        ));

		$this
			->registerCallback('postSave', array($this, 'postSave'))
			->registerCallback('postDelete', array($this, 'postDelete'))
		;
	}

	/**
	 * Copies file from temp location into a location accessible by this model.
	 *
	 * @param Storage $object
	 */
	public function postSave(Storage $object) {
		$object->copyFromTempLocation();
	}

	/**
	 * Deletes this model's file.
	 *
	 * @param Storage $object
	 */
	public function postDelete(Storage $object) {
		$object->deleteFile();
	}

	/**
	 * Convenience method for creating and saving a file into storage.
	 *
	 * @throws File\Exception if the upload was not allowed.
	 *
	 * @param string $filename      The file's name.
	 * @param string $temp_location The location of the file to save.
	 * @param int    $user_id       The user to associate with the file.
	 *
	 * @return Storage
	 */
	public function upload($filename, $temp_location, $user_id) {
		$file = new Storage();
		$file->setFilename($filename);
        try {
            $file->setMimeType(MimetypeMapper::getInstance()->getByFilename($filename));

            /* Check mime type is allowed */
            if (!($file->isUploadAllowed())) {
                throw new File\Exception("Mimetype exists, but is not enabled");
            }
        } catch (File\Exception $e) {
            throw new File\Exception("Your file, '{$file->getFilename()}' is not a valid file type", File\Exception::CODE_MIMETYPE_NOT_FOUND, $e);
        }
		$file->setTempLocation($temp_location);
		$file->setUploadedBy(UserMapper::getInstance()->createModel($user_id));
		$file->loadHashFromTempLocation();
		$file->loadFilesizeFromTempLocation();

		/* Return an existing Storage record if it already exists by hash value
		 * I have to assume that the hash is unique enough :P
		 */
		$existing = $this->findByColumn('hash', $file->getHash());

		if ($existing->count()) {
			return $existing->first();
		}

		/* Check maximum allowed file size */
        if($file->getMimeType()->getMaxFileSize() < $file->getFilesize()) {
            $humanSize =  Alaress_Byte_Format::bytesToString($file->getMimetype()->getMaxFileSize());
            throw new File\Exception("The size of the uploaded file exceeds the file size restrictions of $humanSize.", File\Exception::CODE_FILE_TOO_LARGE);
        }

		$this->save($file);

        /* If video add to video processing queue, note checks ID to ensure its a new file and not an already existing file */
	    if($file->getMimeType()->isVideo() && $file->getId() != 0) {
		    $mapper = new Video\QueueMapper();
	        $videoQ = new Video\Queue();
		    $videoQ->setHash($file->getHash());
		    $videoQ->setStatus( Video\QueueMapper::STATUS_PENDING );
		    $mapper->save($videoQ);
	    }

		return $file;
	}

    /**
     * This method will fetch a storage object via its hash and populate it the object storage and mime object
     *
     * @throws Schoolbox\Mapper\Exception if no instance of model is found
     *
     * @param string $hash The hash identifier of the storage file
     * @return Storage
     *
     */
    public function findByHash($hash) {

        $file = false;

        $mimeTypeMapper = $this->getJoinMapper('allowedFileType');

        $query = "
			SELECT {$this->getColumnString('storage')},
			      {$mimeTypeMapper->getColumnString('m')}
			FROM `{$this->table}`
			LEFT JOIN {$this->getJoinSql('allowedFileType', 'storage', 'm')}
			WHERE hash = ?
		";

        $statement = $this->database->prepare($query);
        $statement->execute(array($hash));

        if (($row = $statement->fetch()) !== false) {
            $file = $this->hydrate($row, 'storage');
            $file->setMimeType($mimeTypeMapper->hydrate($row, 'm'));
        }

        if (!($file instanceof Storage)) {
            throw new Schoolbox\Mapper\Exception('Instance of object not found');
        }

        return $file;

    }


    /**
     * @param object $object
     * @param string $verb
     * @param null   $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        // vicious lies
        return true;
    }

}
