<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\SingleFile;

use Schoolbox\Resource\Storage\GarbageCollector\HardLink;
use Schoolbox\Resource\Storage\GarbageCollector\IGarbageCollector;

class GarbageCollector extends HardLink implements IGarbageCollector
{
    /**
     * @var string
     */
    protected $hashColumn = 'storage_id';
}
