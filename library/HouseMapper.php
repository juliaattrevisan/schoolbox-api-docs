<?php

/**
 * @method House createModel($idValue = null)
 */
class HouseMapper extends Schoolbox\Mapper {
	protected $table = 'house';

	protected function mapping () {
		$this->hasTable('house');
		$this->hasModel(House::class);
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
