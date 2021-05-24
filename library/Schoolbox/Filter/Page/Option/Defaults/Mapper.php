<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter\Page\Option\Defaults;

use Schoolbox\Filter\Page\Option\Mapper as FilterOptionMapper;

/**
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('filter_default');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('filter_option_id', FilterOptionMapper::class, 'id', self::TYPE_MODEL, null, 'filterOption', 'filterOption');
        $this->uses('role_type', self::TYPE_STRING);
        $this->uses('selected', self::TYPE_BOOLEAN);
    }
}
