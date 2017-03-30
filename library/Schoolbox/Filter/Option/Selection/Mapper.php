<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Option\Selection;

use Schoolbox\Filter\Option\Mapper as FilterOptionMapper;
use Schoolbox\UserMapper;

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
        $this->joins('filter_option_id', FilterOptionMapper::class, 'id', self::TYPE_MODEL, null, 'filterOption', 'filterOption');
        $this->joins('user_id', UserMapper::class, 'id', self::TYPE_MODEL, null, 'user', 'user');
        $this->uses('selected', self::TYPE_BOOLEAN);
    }
}
