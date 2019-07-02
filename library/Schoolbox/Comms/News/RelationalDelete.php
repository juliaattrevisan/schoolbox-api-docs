<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News;

use Schoolbox\Resource\Storage\RelationalDelete\IRelationalDelete;
use Schoolbox\Resource\Storage\RelationalDelete\Nullable;

class RelationalDelete extends Nullable implements IRelationalDelete
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
