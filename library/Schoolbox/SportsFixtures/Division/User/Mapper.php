<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Division\User;

use Schoolbox\Mapper As BaseMapper;
use Schoolbox\UserMapper;
use Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;

/**
 * Mapper for the relation between divisions and users.
 *
 * @method Model createModel()
 * @method Model findById($id)
 * @package Schoolbox\SportsFixtures\Division\User
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_division_user');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('division_id', DivisionMapper::class, 'id', null, null, 'division', 'division');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('sequence', self::TYPE_INTEGER);
    }
}
