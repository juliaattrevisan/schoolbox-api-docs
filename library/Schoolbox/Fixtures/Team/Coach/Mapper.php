<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Fixtures\Team\Coach;

use Schoolbox\Collection;
use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Coach\Mapper as CoachMapper;

/**
 * Mapper for the relation between teams and users.
 *
 * @method Model createModel()
 * @method Model findById($id)
 * @method Collection|Model[] findByColumn($columnName, $columnValue, $options = array())
 * @package Schoolbox\Fixtures\Team\Coach
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_team_coach');
        $this->hasModel(Model::class);
        $this->joins('team_id', TeamMapper::class, 'id', null, null, 'team', 'team');
        $this->joins('coach_id', CoachMapper::class, 'id', null, null, 'coach', 'coach');
    }
}
