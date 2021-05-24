<?php

/**
 * Test mapper.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Test;

/**
 * Class SoftDeleteTestMapper
 *
 * @package Schoolbox\Test
 * @method MockDatabase getDatabase()
 */
class SoftDeleteTestMapper extends AbstractTestMapper
{
    protected function mapping()
    {
        $this->hasTable('table');
        $this->hasModel(SoftDeleteTestModel::class);
        $this->hasMap([
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ]);
        $this->hasMap([
            'column'   => 'name',
            'property' => 'name',
            'type'     => self::TYPE_STRING,
        ]);
        $this->hasMap([
            'column'   => 'deleted',
            'property' => 'deleted',
            'type'     => self::TYPE_BOOLEAN,
        ]);
        $this->hasSoftDeleteBooleanField('deleted');
    }
}
