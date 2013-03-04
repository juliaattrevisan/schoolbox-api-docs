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


    /**
     * Saves the given object or collection of objects.
     *
     * Overwritten so that getting and setting properties works with new model
     * classes (which don't have publicly exposed properties, but have getters
     * and setters instead)
     *
     * @param $object
     *
     * @return array|bool
     */
    public function save($object) {
        $collection = $this->getAsCollection($object);
        $table      = $this->table;
        $colString  = $this->getColumnString();
        $plcString  = $this->getPlaceholderString();
        $updString  = $this->getUpdateString();

        $statement = $this->database->prepare("
            INSERT INTO `$table` ($colString)
            VALUES ($plcString)
            ON DUPLICATE KEY UPDATE $updString;
        ");
        $this->database->beginTransaction();

        $affectedIDs = array();
        foreach ($collection as $model) {
            foreach ($this->map as $map) {
                //
                // getProperty($model, $property)
                // setProperty($model, $property, $value)
                $property = 'get'.ucfirst($map['property']);
                $column   = $map['column'];
                $statement->bindParam(":$column", $model->$property());
            }
            if ($statement->execute()) {
                $get_identifier = 'get'.ucfirst($this->identifier['property']);
                if (!$model->$get_identifier()) {
                    $set_identifier = 'set'.ucfirst($this->identifier['property']);
                    $model->$set_identifier($this->database->lastInsertId());
                }
                $affectedIDs[] = $model->$get_identifier();
            }
            else {
                $this->database->rollBack();
                return false;
            }
        }
        $this->database->commit();
        return $affectedIDs;
}


    /**
     * Deletes the object or objects.
     *
     * Overwritten so that mapper can access properties via getters/setters
     * instead of directly.
     *
     * @param $object
     *
     * @return bool
     */
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