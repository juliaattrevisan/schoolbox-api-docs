<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter\Page\Option;

use Schoolbox\Filter;
use Schoolbox\GenericCollection;

/**
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] createCollection($contents = [])
 * @method GenericCollection|Model[] findByColumn($columnName, $columnValue, $options = [])
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('filter_option');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('filter_page_id', Filter\Page\Mapper::class, 'id', self::TYPE_MODEL, null, 'filterPage', 'filter_page');
        $this->uses('key', self::TYPE_STRING);
        $this->uses('available', self::TYPE_BOOLEAN);

        $this->joinsReverseMulti('filter_option_id', Filter\Page\Option\Defaults\Mapper::class, 'defaults', 'defaults');

        $this->registerCallback(self::CALLBACK_PREDELETE, [$this, 'hardDeleteRoleDefaults']);
    }


    /**
     * Pre hard-delete callback to hard-delete any RoleDefaults for this option
     *
     * @param Model $model
     * @return bool
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     */
    public function hardDeleteRoleDefaults(Model $model)
    {
        /** @var Filter\Page\Option\Defaults\Mapper $defaultsMapper */
        $defaultsMapper = $this->getJoinMapper('defaults');
        $defaults       = $defaultsMapper->findByColumn('filter_option_id', $model->getId());
        $defaultsMapper->delete($defaults);
        return true;
    }
}
