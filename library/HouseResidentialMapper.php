<?php

/**
 * @method HouseResidential createModel($idValue = null)
 */
class HouseResidentialMapper extends Schoolbox\Mapper {
	protected $table = 'luResidentialHouse';

	protected function mapping () {
		$this->hasTable('luResidentialHouse');
		$this->hasModel(HouseResidential::class);
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

    /**
     * This mapper does not raise notifications.
     *
     * @param object $object
     * @param string $verb
     * @param null   $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }
}
