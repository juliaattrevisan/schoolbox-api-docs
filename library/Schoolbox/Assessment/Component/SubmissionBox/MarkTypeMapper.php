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

class MarkTypeMapper extends Schoolbox\Mapper {

	protected function mapping() {
		$this->hasTable('submission_mark_type');
		$this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBox\\MarkType');
		$this->hasMap(array(
			'column'     => 'mark_type_id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'hr_name',
			'property'   => 'name',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'slug',
			'property'   => 'slug',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'comment',
			'property'   => 'comment',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'enabled',
			'property'   => 'enabled',
			'type'       => self::TYPE_BOOLEAN,
		));
	}

	public function getAll($include_disabled = false) {
		$query = "SELECT {$this->getColumnString('m')} FROM {$this->getTable()} m";
		if (!$include_disabled) {
			$query .= ' WHERE enabled IS TRUE';
		}
		$statement = $this->database->prepare($query);
		$statement->execute();
		$collection = new Schoolbox\Collection(array());
		foreach ($statement as $row) {
			$collection->add($this->hydrate($row, 'm'));
		}
		return $collection;
	}
}
