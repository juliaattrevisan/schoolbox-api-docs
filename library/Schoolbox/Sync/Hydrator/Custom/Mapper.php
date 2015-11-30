<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Hydrator\Custom;

use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for custom hydrators
 * @method Model createModel()
 */
class Mapper extends BaseMapper
{
    /** @var Factory */
    protected $hydratorFactory;


    /**
     * Overrides constructor as public, so that new instances can be created
     * without going through getInstance().
     */
    public function __construct()
    {
        parent::__construct();
        $this->hydratorFactory = new Factory();
    }


    /**
     * @inheritDoc
     */
    protected function mapping()
    {
        $this->hasPrototype(new Model());
        $this->hasTable('ups_custom_hydrator');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('class', self::TYPE_STRING);
        $this->uses('role_type', self::TYPE_STRING);
        $this->uses('enabled', self::TYPE_BOOLEAN);
        $this->uses('sequence', self::TYPE_INTEGER);
    }


    /**
     * Hydrates a row from the database into a model.
     *
     * @param array    $row      The row from the database.
     * @param string   $alias    The prefix of this mapper's table cells in $row
     * @param callable $callback If supplied and callable, this method is
     *                           applied to the model once it's retrieved.
     *                           It takes the following parameters:
     *                           $mapper: this object
     *                           $model:  the model that has been hydrated
     *                           $row:    the row from the database
     *
     * @throws BaseMapper\Exception
     * @return Model
     */
    public function hydrate(array $row, $alias = '', $callback = null)
    {
        /** @var Model $model */
        $model = parent::hydrate($row, $alias, $callback);
        $model->setHydrator($this->hydratorFactory->produceFromModel($model));
        return $model;
    }

}
