<?php

/**
 * MSSQL Iterator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Iterator\Sqlsrv;

use Alaress\Database\Iterator\Base;

class Iterator extends Base
{
    /**
     * @inheritdoc
     */
    public function rewind()
    {
        // Have disabled rewinding due to performance issues with cursors being enabled
    }
}
