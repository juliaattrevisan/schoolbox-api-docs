<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News;

use Schoolbox\Resource\Storage\GarbageCollector\IGarbageCollector;
use Schoolbox\Resource\Storage\GarbageCollector\Nullable;

class GarbageCollector extends Nullable implements IGarbageCollector
{
    /**
     * @var string
     */
    protected $hashColumn = 'feature_image_hash';

    /**
     * @var array
     */
    protected $setMethods = ['setFeatureImage'];
}
