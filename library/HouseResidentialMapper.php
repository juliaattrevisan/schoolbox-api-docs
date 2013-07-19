<?php

class HouseResidentialMapper extends Schoolbox\Mapper {
	protected $table = 'luResidentialHouse';

	protected function mapping () {
		$this->hasTable('luResidentialHouse');
		$this->hasModel('HouseResidential');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'name',
			'property'   => 'house',
			'type'       => self::TYPE_STRING,
		));
	}
}
