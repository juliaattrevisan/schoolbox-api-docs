<?php

class HouseMapper extends Schoolbox\Mapper {
	protected $table = 'house';

	protected function mapping () {
		$this->hasTable('house');
		$this->hasModel('House');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'house',
			'property'   => 'house',
			'type'       => self::TYPE_STRING,
		));
	}
}
