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

	public function delete($object) {
		$id_column  = $this->identifier['column'];
		$collection = $this->getAsCollection($object);
		$statement  = $this->database->prepare(
			"DELETE FROM `{$this->table}` WHERE `{$id_column}` = :id_value"
		);
		foreach ($collection as $model) {
			$property_method = 'get'.ucfirst($this->identifier['property']);
			$statement->bindParam(':id_value', $model->$property_method());
			if (!$statement->execute()) {
				return false;
			}
		}
		return true;
	}
}