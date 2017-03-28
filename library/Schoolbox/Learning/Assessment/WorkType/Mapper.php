<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\WorkType;

use Schoolbox;


/**
 * @method Model findById($identifierValue)
 */
class Mapper extends Schoolbox\Mapper
{

    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('lu_work_type');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
    }


    /**
     * @param int $id
     * @return string|bool
     */
    public function getNameFromId($id)
    {
        $query     = "SELECT `name` FROM {$this->getTable()} t WHERE t.id=?";
        $statement = $this->database->prepare($query);
        $statement->execute([$id]);
        if ($row = $statement->fetch()) {
            return $row['name'];
        }
        return false;
    }


    /**
     * @param int $type
     * @return Schoolbox\GenericCollection
     * @throws Schoolbox\Mapper\Exception
     */
    public function getAllExcept($type)
    {
        $query     = "
            SELECT {$this->getColumnString('t')}
            FROM {$this->getTable()} t
            WHERE t.id != ?
        ";
        $statement = $this->database->prepare($query);
        $statement->execute([$type]);
        $collection = $this->createCollection();
        foreach ($statement as $row) {
            $collection->add($this->hydrate($row, 't'));
        }
        return $collection;
    }
}
