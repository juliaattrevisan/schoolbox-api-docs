<?php

use Schoolbox\Mapper\Exception\CallbackException;

/**
 * @method House createModel($idValue = null)
 */
class HouseMapper extends Schoolbox\Mapper
{
    protected $table = 'house';

    protected function mapping()
    {
        $this->hasTable('house');
        $this->hasModel(House::class);
        $this->hasMap([
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ]);

        $this->hasMap([
            'column'   => 'house',
            'property' => 'house',
            'type'     => self::TYPE_STRING,
        ]);

        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('updated_by', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'validateName']);
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

    /** @inheritDoc */
    protected function getDefaultOrderByParams(): array
    {
        return ['sequence' => 'ASC'];
    }

    /**
     * Validates that the name is not empty and is unique
     *
     * @param House $model
     * @return bool
     * @throws CallbackException
     */
    protected function validateName(House $model): bool
    {
        if (trim($model->getHouse() ?? '') === '') {
            throw new CallbackException("House name cannot be empty");
        }

        if (!$this->isUnique(['house' => $model->getHouse()], $model->getId())) {
            throw new CallbackException(
                sprintf(
                    'A house with the name "%s" already exists, you must have a unique name',
                    $model->getHouse()
                )
            );
        }

        return true;
    }
}
