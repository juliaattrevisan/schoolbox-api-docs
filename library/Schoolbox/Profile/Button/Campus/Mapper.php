<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Profile\Button\Campus;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('profile_button_campus');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('profile_button_id', self::TYPE_INTEGER);
        $this->uses('campus_id', self::TYPE_INTEGER);
    }
}
