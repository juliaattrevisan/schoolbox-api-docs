<?php
/**
 * Mapper for user titles in Schoolbox.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\User\Title;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    /**
     * {@inheritdoc}
     */
    public function mapping()
    {
        $this->hasTable('luTitle');
        $this->hasModel(Model::class);
        $this->hasMap(
            array(
                'column'     => 'titleID',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'titleName',
                'property' => 'title',
                'type'     => self::TYPE_STRING,
            )
        );
    }
}
