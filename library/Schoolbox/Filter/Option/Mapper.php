<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Option;

use Schoolbox\Filter\Mapper as FilterMapper;
use Schoolbox\GenericCollection;

/**
 * @method Model createModel($idValue = null)
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
        $this->joins('filter_id', FilterMapper::class, 'id', self::TYPE_MODEL, null, 'filter', 'filter');
        $this->uses('page', self::TYPE_STRING);
        $this->uses('key', self::TYPE_STRING);
        $this->uses('available', self::TYPE_BOOLEAN);
    }
}
