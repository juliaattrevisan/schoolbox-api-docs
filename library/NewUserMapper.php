<?php

class NewUserMapper extends Mapper {
    protected function mapping () {

        $this->hasTable('user');
        $this->hasModel('NewUser');

        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => Mapper::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'username',
            'property'   => 'username',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'password',
            'property'   => 'password',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'group_id',
            'property'   => 'groupId',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'title',
            'property'   => 'title',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'firstname',
            'property'   => 'firstname',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'lastname',
            'property'   => 'lastname',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'email_pass',
            'property'   => 'emailPass',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'synergy_id',
            'property'   => 'synergyId',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'campus',
            'property'   => 'campus',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'year',
            'property'   => 'year',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'house',
            'property'   => 'house',
            'type'       => Mapper::TYPE_INTEGER,
        ));

		$this->hasMap(array(
			'column' => 'residential_house_id',
			'property' => 'residentialHouseId',
			'type' => Mapper::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column' => 'boarding_status',
			'property' => 'boardingStatus',
			'type' => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
            'column'     => 'local_email',
            'property'   => 'localEmail',
            'type'       => Mapper::TYPE_BOOLEAN,
        ));

        $this->hasMap(array(
            'column'     => 'alt_email',
            'property'   => 'altEmail',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'mail_handler',
            'property'   => 'mailHandler',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'hash',
            'property'   => 'hash',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'key',
            'property'   => 'key',
            'type'       => Mapper::TYPE_STRING,
        ));

        $this->hasMap(array(
            'column'     => 'eportfolio_enabled',
            'property'   => 'eportfolioEnabled',
            'type'       => Mapper::TYPE_STRING,
        ));

        parent::mapping();
    }
}