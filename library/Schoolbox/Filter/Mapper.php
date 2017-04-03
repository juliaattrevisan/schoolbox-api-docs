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

/** @method Model createModel($idValue = null) */
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
    public function findForClass($class)
    {
        return $this->findByColumn('class', $class)->first();
    }


    /**
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     * @throws MapperException
     */
    public function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var Model $model */
            $model = $this->hydrate($row);
            $c->add($model);
        }

        return $c;
    }
}
