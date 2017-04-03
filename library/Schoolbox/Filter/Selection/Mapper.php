<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Selection;

use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('filter_selection');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('filter_id', Filter\Mapper::class, 'id', self::TYPE_MODEL, null, 'filter', 'filter');
        $this->joins('user_id', UserMapper::class, 'id', self::TYPE_MODEL, null, 'user', 'user');
        $this->uses('page', self::TYPE_STRING);
        $this->uses('key', self::TYPE_STRING);
        $this->uses('selected', self::TYPE_BOOLEAN);
    }
}
