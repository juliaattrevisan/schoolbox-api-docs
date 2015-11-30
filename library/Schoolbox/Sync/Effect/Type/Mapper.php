<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Effect\Type;


use Schoolbox;

class Mapper extends Schoolbox\Mapper
{
    /* As per ups_effect_type in the db
     * Used to determine Effect Fragment templates
     */
    const EFFECT_TYPE_LITERAL = 2;

    /* Skip Effect Type
     */
    const EFFECT_TYPE_SKIP = 3;


    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasPrototype(new Model());
        $this->hasTable('ups_effect_type');

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);

    }

}