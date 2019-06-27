<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile;

use Schoolbox\Resource\Storage\GarbageCollector\Deletable;
use Schoolbox\Resource\Storage\GarbageCollector\IGarbageCollector;

class GarbageCollector extends Deletable implements IGarbageCollector
{

}
