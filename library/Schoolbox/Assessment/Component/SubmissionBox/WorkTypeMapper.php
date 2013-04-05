<?php
/**
 * Mapper for the SubmissionBox\WorkType model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox;

class WorkTypeMapper extends Schoolbox\Mapper {

	protected function mapping () {
		$this->hasTable('file_type');
		$this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBox\\WorkType');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'name',
			'property'   => 'name',
			'type'       => self::TYPE_STRING,
		));
	}

	public function getNameFromId($id) {
		$query = "SELECT `name` FROM {$this->getTable()} t WHERE t.id=?";
		$statement = $this->database->prepare($query);
		$statement->execute(array($id));
		if ($row = $statement->fetch()) {
			return $row['name'];
		}
		return false;
	}

	public function getAll() {
		$query = "SELECT {$this->getColumnString('t')} FROM {$this->getTable()} t";
		$statement = $this->database->prepare($query);
		$statement->execute();
		$collection = new Schoolbox\Collection(array());
		foreach ($statement as $row) {
			$collection->add($this->hydrate($row, 't'));
		}
		return $collection;
	}

	public function getAllExcept($type) {
		$query = "
			SELECT {$this->getColumnString('t')}
			FROM {$this->getTable()} t
			WHERE t.id != ?
		";
		$statement = $this->database->prepare($query);
		$statement->execute(array($type));
		$collection = new Schoolbox\Collection(array());
		foreach ($statement as $row) {
			$collection->add($this->hydrate($row, 't'));
		}
		return $collection;
	}
}
