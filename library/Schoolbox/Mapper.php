<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;
use Schoolbox\Column\Factory as ColumnFactory;
use \Mapper as BaseMapper;
use Schoolbox\Column\IColumnType;
use Schoolbox\Column\IFactory;

class Mapper extends BaseMapper {

    /**
     * Factory for building column hydrators.
     *
     * @var IFactory
     */
    protected $column_factory;

    public function __construct() {
        //
        // Move to setup method?
        $this->setColumnFactory(new ColumnFactory());
        parent::__construct();
    }

    /**
     * @param IFactory $column_factory
     *
     * @return $this
     */
    public function setColumnFactory(IFactory $column_factory) {
        $this->column_factory = $column_factory;
        return $this;
}

    /**
     * @return IFactory
     */
    public function getColumnFactory() {
        return $this->column_factory;
    }

    /**
     * Updated because hydrated objects won't usually belong to the Model class.
     * Collections will always be \Schoolbox\Collection instances for these
     * classes, though.
     *
     * @param mixed $object
     *
     * @return Collection
     */
    public function getModelAsCollection($object) {
        if ($object instanceof Collection) {
            return $object;
        }
        return new Collection(array($object));
    }

    /**
     * Overridden to use getter and hydrator instead of getting property
     * directly.
     *
     * @param object $model
     * @param string $property
     * @param array  $map
     *
     * @return mixed
     */
    protected function getModelProperty($model, $property, $map) {
        $get_method = 'get'.ucfirst($property);
        $value = $model->$get_method();
        if ($map['hydrator'] instanceof IColumnType) {
            $value = $map['hydrator']->dehydrate($value);
        }
        return $value;
    }

    /**
     * Overridden to use setter and hydrator instead of setting property
     * directly.
     *
     * @param object $model
     * @param string $property
     * @param mixed  $value
     * @param array  $map
     *
     * @return $this
     */
    protected function setModelProperty($model, $property, $value, $map) {
        $set_method = 'set'.ucfirst($property);
        if ($map['hydrator'] instanceof IColumnType) {
            $value = $map['hydrator']->hydrate($value);
        }
        $model->$set_method($value);
        return $this;
    }

    /**
     * Overrides parent::hasMap() to set the hydrator based on the field type.
     *
     * @param $map
     * @return bool
     */
    protected function hasMap($map) {
        if (is_array($map) && !isset($map['hydrator'])) {
            $map['hydrator'] = $this->column_factory->build($map['type']);
        }
        return parent::hasMap($map);
    }
}