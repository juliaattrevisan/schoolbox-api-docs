<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Policy;

/**
 * @method Model findOneByColumn($columnName, $columnValue, $order = array(), $options = array())
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('comms_news_policy');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('identifier', self::TYPE_STRING);
        $this->uses('value', self::TYPE_STRING);
    }
}
