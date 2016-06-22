<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Position;

use Schoolbox\Mapper As BaseMapper;
use Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use Schoolbox\SportsFixtures\Position\Model as Position;


/**
 * Class Mapper
 * @method Position createModel(int $idValue = null)
 * @package Schoolbox\SportsFixtures\Position
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_position');
        $this->hasModel(Position::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('sport_id', SportMapper::class, 'id', null, null, 'sport', 'sport');
        $this->uses('name', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


}