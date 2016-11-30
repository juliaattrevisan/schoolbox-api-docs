<?php
/**
 * ExtensionMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\File;

use Schoolbox\Collection;

/**
 * Class ExtensionMapper
 *
 * @package Schoolbox\File
 * @method Collection|Extension[] findByColumn($columnName, $columnValue, $options = [])
 */
class ExtensionMapper extends \Schoolbox\Mapper {

	/**
	 * Mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('allowedFileExtension');
		$this->hasModel(Extension::class);

		$this->hasMap(array(
			'column'     => 'fileTypeId',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'extension',
			'property'   => 'extension',
			'type'       => self::TYPE_STRING,
		));
	}

	/**
	 * All the allowed file extensions
	 *
	 * @return \Schoolbox\Collection
	 */
	public function findAllowedExtensions() {
		$query = "
			SELECT {$this->getColumnString()}
			FROM allowedFileExtension
		";

		$statement = $this->getDatabase()->prepare($query);
		$statement->execute();

		$collection = new \Schoolbox\Collection();

		foreach ($statement as $row) {
			$extension = $this->hydrate($row);

			$collection->add($extension);
		}

		return $collection;
	}
}
