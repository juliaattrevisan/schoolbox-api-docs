<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\SportsFixtures\Status;

use Schoolbox\Mapper As BaseMapper;
use Schoolbox\SportsFixtures\Status\Model as Status;

/**
 * Class Mapper
 * @method Status createModel(int $idValue = null)
 * @package Schoolbox\SportsFixtures\Status
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_status');
        $this->hasModel(Status::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }
}