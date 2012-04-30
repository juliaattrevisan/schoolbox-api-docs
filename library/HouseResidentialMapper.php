<?php

class HouseMapper extends Mapper {
	protected $table = 'luResidentialHouse';

    protected function mapping () {
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => Mapper::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'name',
            'property'   => 'house',
            'type'       => Mapper::TYPE_STRING,
        ));

        parent::mapping();
    }
}
