<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace SocialStream\Post\Url;

use Schoolbox\Resource\Storage\RelationalDelete\IRelationalDelete;
use Schoolbox\Resource\Storage\RelationalDelete\Nullable;

class RelationalDelete extends Nullable implements IRelationalDelete
{
    /**
     * @var string
     */
    protected $hashColumn = 'url';

    /**
     * @var array
     */
    protected $setMethods = ['setUrl'];

    /**
     * @var string
     */
    protected $hashPrefix = '/storage/image.php?size=maxsize&hash=';
}
