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
 * @method Model createModel($idValue = null)
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
        $this->uses('external_result_type', self::TYPE_STRING);
        $this->uses('show_on_grades_page', self::TYPE_BOOLEAN);
    }


    /**
     * @param int $id
     * @return string|bool
     */
    public function findNameById($id)
    {
        try {
            return $this->selectScalarByColumnList('name', ['id' => $id])->fetchColumn();
        } catch (\UnderflowException $e) {
            return false;
        }
    }


    /**
     * @param int $id
     * @return Schoolbox\GenericCollection
     * @throws Schoolbox\Mapper\Exception
     */
    public function findAllExcept($id)
    {
        return $this->findByPredicates($this->getPredicateBuilder()->notEquals('id', $id));
    }
}
