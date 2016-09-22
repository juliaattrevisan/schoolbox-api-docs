<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Import;

use Schoolbox\Pastoral\Log\Model as PastoralLogModel;
use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_import');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('status', self::TYPE_STRING);
        $this->uses('result', self::TYPE_STRING);
        $this->uses('updated_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME);
    }
}
