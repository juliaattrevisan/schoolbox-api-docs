<?php
/**
 * Mapper for class objects.
 *
 * This is only used by the Schoolbox connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2013 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External;
use Schoolbox;

class ClassEntryMapper extends Schoolbox\Mapper {

    /**
     * {@inheritdoc}
     */
    protected function mapping() {
        $this->hasTable('class');
        $this->hasModel('\\Schoolbox\\External\\ClassEntry');
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true
        ));
        $this->hasMap(array(
            'column'   => 'name',
            'property' => 'name',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'code',
            'property' => 'code',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'description',
            'property' => 'description',
            'type'     => self::TYPE_STRING,
        ));
    }
}
