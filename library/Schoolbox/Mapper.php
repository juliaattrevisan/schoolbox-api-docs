<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;
use Schoolbox;

class Mapper extends \Mapper {
    public function __construct(){
        parent::__construct();
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
    public function getAsCollection($object) {
        if ($object instanceof Collection) {
            return $object;
        }
        return new Collection(array($object));
    }

    /**
     * Overridden to use getter instead of getting property directly.
     *
     * @param object $model
     * @param string $property
     *
     * @return mixed
     */
    protected function getModelProperty($model, $property) {
        $get_method = 'get'.ucfirst($property);
        return $model->$get_method();
    }

    /**
     * Overridden to use setter instead of setting property directly.
     *
     * @param object $model
     * @param string $property
     * @param mixed  $value
     *
     * @return void
     */
    protected function setModelProperty($model, $property, $value) {
        $set_method = 'set'.ucfirst($property);
        return $model->$set_method($value);
    }
}