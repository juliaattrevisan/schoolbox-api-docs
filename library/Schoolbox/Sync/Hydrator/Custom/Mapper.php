<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Hydrator\Custom;

use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for custom hydrators
 *
 * @method Model hydrate(array $row, $alias = '', $callback = '')
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritDoc
     */
    protected function mapping()
    {
        $this->hasPrototype(new Model());
        $this->hasTable('ups_custom_hydrator');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('class', self::TYPE_STRING);
        $this->uses('role_type', self::TYPE_STRING);
        $this->uses('enabled', self::TYPE_BOOLEAN);
        $this->uses('sequence', self::TYPE_INTEGER);
    }
}
