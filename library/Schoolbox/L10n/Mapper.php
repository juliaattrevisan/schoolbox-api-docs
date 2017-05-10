<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\L10n;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('localisation');
        $this->hasModel(Model::class);
        $this->uses('term', self::TYPE_STRING, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('singular_form', self::TYPE_STRING);
        $this->uses('plural_form', self::TYPE_STRING);
        $this->uses('capitalise_first', self::TYPE_BOOLEAN);
        $this->uses('indefinite_article', self::TYPE_STRING);
    }
}
