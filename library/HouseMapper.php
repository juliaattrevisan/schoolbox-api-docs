<?php

class HouseMapper extends Mapper {
    protected function mapping () {
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => Mapper::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'house',
            'property'   => 'house',
            'type'       => Mapper::TYPE_STRING,
        ));

        parent::mapping();
    }
}
