<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Eportfolio;

use Schoolbox\Resource\Storage\GarbageCollector\HardLink;
use Schoolbox\Resource\Storage\GarbageCollector\IGarbageCollector;

class GarbageCollector extends HardLink implements IGarbageCollector
{
    protected $hashColumn = 'imagehash';
}
