<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task;

use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\Resource\Storage\IStorage;

/**
 * Class File
 *
 * Domain representation of a file attached to a Task intance
 *
 * @package Schoolbox\Learning\Assessment\Task
 */
class File implements IStorage
{
    use StorableTrait;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
}
