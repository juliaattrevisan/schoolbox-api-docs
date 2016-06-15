<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Division;

use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;
use Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use Schoolbox\SportsFixtures\Division\Model as DivisionModel;

class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        /**
         * Divisions (eg. Soccer First, Seconds, Thirds)
         */
        $this->hasTable('sportsfixtures_division');
        $this->hasModel(DivisionModel::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);

        $this->joins('sport_id', SportMapper::class, 'id', null, null, 'sport', 'sport');

        $this->uses('name', self::TYPE_STRING);
        $this->uses('url', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME);
        $this->uses('updated_at', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME);

        $this->hasSoftDeleteDateField('deleted_at');
    }

    /**
     * Find all divisions for a given sport
     *
     * @param $id
     * @return \Schoolbox\Collection
     */
    public function findAllBySport($id) {
        return $this->findByColumn("sport_id", $id);
    }
}