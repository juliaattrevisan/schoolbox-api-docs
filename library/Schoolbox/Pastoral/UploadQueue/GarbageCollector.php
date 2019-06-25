<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Pastoral\UploadQueue;

use Schoolbox\Resource\Storage\GarbageCollector\HardLink;
use Schoolbox\Resource\Storage\GarbageCollector\IGarbageCollector;

class GarbageCollector extends HardLink implements IGarbageCollector
{

}
