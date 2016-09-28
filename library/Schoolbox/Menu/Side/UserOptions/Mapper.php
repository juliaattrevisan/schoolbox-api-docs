<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Menu\Side\UserOptions;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('menu_side_user_options');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('user_id', self::TYPE_INTEGER);
        $this->uses('show_bookmarks', self::TYPE_INTEGER);
        $this->uses('show_groups', self::TYPE_INTEGER);
        $this->uses('show_subjects', self::TYPE_INTEGER);
    }
}
