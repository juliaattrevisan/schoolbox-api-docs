<?php
/**
 * TextboxMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Textbox;


class Mapper extends \Schoolbox\Mapper
{

    /**
     * Mapping
     *
     * @return Bool|void
     */
    protected function mapping()
    {
        $this->hasTable('textbox');
        $this->hasModel('\\Schoolbox\\Textbox');

        $this->hasMap(
            array(
                'column'     => 'textBoxID',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'categoryID',
                'property' => 'categoryId',
                'type'     => self::TYPE_MODEL,
                'join'     => 'category'
            )
        );

        $this->hasMap(
            array(
                'column'   => 'heading',
                'property' => 'heading',
                'type'     => self::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'body',
                'property' => 'body',
                'type'     => self::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column'   => 'deleted',
                'property' => 'deletedAt',
                'type'     => self::TYPE_STRING,
            )
        );

        $this->hasSoftDeleteDateField('deleted');
    }
}