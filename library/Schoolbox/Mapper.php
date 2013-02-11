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
     * @param Array $row
     * @param String $alias
     * @param Function $callback
     * @return Model
     */
    public function hydrate($row, $alias = '', $callback = null) {
        $model = new $this->model;

        foreach ($this->map as $map) {
            $propertyName = 'set'.ucfirst($map['property']);
            $columnName = $map['column'];

            if (!empty($alias)) {
                $columnName = "$alias.$columnName";
            }



            $model->$propertyName($row[$columnName]);
        }

        if (!empty($callback)) {
            $model = $callback($this, $model, $row);
        }

        return $model;
    }
}