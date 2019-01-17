<?php
/**
 * File
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork;


use Alaress_Date_Format;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\Resource\Storage\IStorage;

class File implements IStorage
{
    use StorableTrait;

    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->storage->getCreatedAt();
    }
}
