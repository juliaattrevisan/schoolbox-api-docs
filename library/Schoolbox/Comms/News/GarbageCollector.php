<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News;

use Schoolbox\Resource\Storage\GarbageCollector\IGarbageCollector;
use Schoolbox\Resource\Storage\GarbageCollector\SoftLink;

class GarbageCollector extends SoftLink implements IGarbageCollector
{
    protected $hashColumn = 'feature_image_hash';

    protected $setMethods = ['setFeatureImage'];
}
