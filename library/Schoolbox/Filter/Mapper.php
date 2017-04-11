<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('filter');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('class', self::TYPE_STRING);
    }


    /**
     * @param string $class
     * @return Model
     */
    public function findByClass($class)
    {
        return $this->findByColumn('class', $class)->first();
    }

}
