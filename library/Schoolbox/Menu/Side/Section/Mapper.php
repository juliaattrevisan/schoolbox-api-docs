<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Menu\Side\Section;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('menu_side_section');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('section', self::TYPE_STRING);
        $this->uses('sort_order', self::TYPE_INTEGER);
        $this->uses('visible', self::TYPE_BOOLEAN);
        $this->uses('title', self::TYPE_STRING);
    }

    /**
     * Return default section names
     * @return array
     */
    public static function getSectionNameDefaults() {

        // TODO SA-8354: inject this
        $config = new \Schoolbox\Config\Loader();

        return [
            'subjects' => 'My Subjects',
            'menu' => $config->get('title'),
            'children' => 'My Students',
            'groups' => 'My Groups',
            'bookmarks' => 'My Links'
        ];
    }
}
