<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;

/**
 * @method GenericCollection|Model[] findAll($options = [])
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
        $this->uses('entity', self::TYPE_STRING);
        $this->uses('class', self::TYPE_STRING);
    }

    /**
     * Find all filter instances that relate to the specified entity
     *
     * @param string $entity  The mapper class for the filtered entity
     * @return GenericCollection|Model[]
     */
    public function findForEntity($entity)
    {
        return $this->findByColumn('entity', $entity);
    }


    /**
     * @param string|string[] $class
     * @return Collection|Model[]
     */
    public function findByClass($class)
    {
        return $this->findByColumn('class', $class);
    }

}
