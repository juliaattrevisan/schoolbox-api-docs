<?php
/**
 * Error messages
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Error;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    public function mapping()
    {
        $this->hasTable('error');
        $this->hasPrototype(new Model());

        $this->hasMap(
            array(
                'column' => 'id',
                'property' => 'id',
                'type' => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );

        $this->hasMap(
            array(
                'column' => 'message',
                'property' => 'message',
                'type' => self::TYPE_STRING,
            )
        );
    }

    /**
     * Return error messages
     *
     * @param Int $id
     * @return string
     */
    public function getMessageById($id)
    {
        return $this->findById($id)->getMessage();
    }
}
