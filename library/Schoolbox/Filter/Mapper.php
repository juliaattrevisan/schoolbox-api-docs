<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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

        $this->joinsReverseMulti('filter_id', Page\Mapper::class, 'pages', 'pages');

        $this->registerCallback(self::CALLBACK_PREDELETE, [$this, 'hardDeletePages']);
    }


    /**
     * Pre hard-delete callback to hard-delete any pages using this filter
     *
     * @param Model $model
     * @return bool
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     */
    public function hardDeletePages(Model $model)
    {
        /** @var Page\Mapper $pageMapper */
        $pageMapper = $this->getJoinMapper('pages');
        $pages      = $pageMapper->findByColumn('filter_id', $model->getId());
        $pageMapper->delete($pages);
        return true;
    }


    /**
     * Find all filter instances that relate to the specified entity
     *
     * @param string $entity The mapper class for the filtered entity
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
