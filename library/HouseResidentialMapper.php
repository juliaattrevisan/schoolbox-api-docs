<?php

/**
 * @method HouseResidential createModel($idValue = null)
 */
class HouseResidentialMapper extends Schoolbox\Mapper
{
    protected $table = 'luResidentialHouse';

    protected function mapping()
    {
        $this->hasTable('luResidentialHouse');
        $this->hasModel(HouseResidential::class);
        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true
            )
        );

        $this->hasMap(
            array(
                'column'   => 'name',
                'property' => 'house',
                'type'     => self::TYPE_STRING,
            )
        );

        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('updated_by', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * This mapper does not raise notifications.
     *
     * @param object $object
     * @param string $verb
     * @param null $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }

    /** @inheritDoc */
    protected function getDefaultOrderByParams(): array
    {
        return ['sequence' => 'ASC'];
    }
}
