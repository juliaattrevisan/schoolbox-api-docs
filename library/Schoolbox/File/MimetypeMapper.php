<?php
/**
 * Mimetype mapper stub.
 *
 * @todo make this a full class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\File;
use Schoolbox;

class MimetypeMapper extends Schoolbox\Mapper {

	protected function mapping() {
		$this->hasTable('allowedFileTypes');
		$this->hasModel('\\Schoolbox\\File\\Mimetype');

		$this->hasMap(array(
			'column'     => 'fileTypeId',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));

		$this->hasMap(array(
			'column'     => 'description',
			'property'   => 'description',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'mime',
			'property'   => 'mime',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'iconFile',
			'property'   => 'iconFile',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'inline',
			'property'   => 'inline',
			'type'       => self::TYPE_BOOL_ENUM,
		));

		$this->hasMap(array(
			'column'     => 'enable',
			'property'   => 'enable',
			'type'       => self::TYPE_BOOL_ENUM,
		));

        $this->hasMap(array(
            'column'     => 'max_file_size',
            'property'   => 'maxFileSize',
            'type'       => self::TYPE_INTEGER,
        ));

        $this->uses('deleted', self::TYPE_BOOLEAN, self::COLUMN_SOFTDELETE);
	}

	/**
	 * Retrieves whether filename has a valid mimetype.
	 *
	 * @param  string $filename
	 * @return bool
	 */
	public function isFilenameMimetypeAllowed($filename) {
		try {
			$mimetype = $this->getByFilename($filename);
			return ($mimetype instanceof Mimetype && $mimetype->getEnable() && !$mimetype->isDeleted());
		}
		catch (Exception $ex) {
			return false;
		}
	}

	/**
	 * Used to fetch a mime object for a given filename
	 *
	 * @param  string $filename
	 * @throws Exception if the mimetype wasn't found
	 * @return Mimetype
	 */
	public function getByFilename($filename) {

		// Copied from AllowedFile methods getExtension() and getMimetype()
		$extension = pathinfo($filename, PATHINFO_EXTENSION);
		$query = "
			SELECT {$this->getColumnString('aft')}
			FROM allowedFileExtension afe
			LEFT JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
			WHERE extension LIKE ?
		";
		$statement = $this->database->prepare($query);
		$statement->execute(array($extension));

		/* Return a mimetype instance for a known mimetype
		 */
		if ($row = $statement->fetch()) {
			return $this->hydrate($row, 'aft');
		}

		throw new Exception("No mime found for extension '$extension'.", Exception::CODE_MIMETYPE_NOT_FOUND);
	}

	/**
	 * For an unknown mime type return an instance with Binary Data
	 * hardcoded in it, because people can mangle Allowed File lists in
	 * the admin system
	 */
	public function getUnknownMime() {
		$mimeType = new Mimetype();
		$mimeType->setDescription('Binary Data (Unknown)');
		$mimeType->setMime('application/octet-stream');
		$mimeType->setInline(false);
		$mimeType->setEnable(true);
		$mimeType->setIconFile('binary.png');
		$mimeType->setMaxFileSize(0);

		return $mimeType;
	}


    /**
     * @param array    $row
     * @param string   $alias
     * @param callable $callback
     * @return object|void
     * @throws Schoolbox\Mapper\Exception
     */
    public function hydrate(array $row, $alias = '', $callback = null)
    {
        if (!is_callable($callback)) {
            // unless a callback is defined, the default behaviour is to ensure that SOME kind of valid mime is returned
            $callback = function ($self, $model, $row) {
                // if it correctly hydrated an entity, return it
                if ($model->getFileTypeID()) {
                    return $model;
                }

                // otherwise return MimeUnknown: never return a Mime[null,null,null,null,...] object
                return $self->getUnknownMime();
            };
        }

        return parent::hydrate($row, $alias, $callback);
    }

}
